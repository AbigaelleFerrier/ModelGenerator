<?php



$sql_DBName = (isset($_POST['sql_DBName']))? $_POST['sql_DBName'] : die;
$sql_DBUser = (isset($_POST['sql_DBUser']))? $_POST['sql_DBUser'] : 'root';
$sql_DBPsw  = (isset($_POST['sql_DBPsw'])) ? $_POST['sql_DBPsw']  : '';
$sql_BDport = (isset($_POST['sql_BDport']))? $_POST['sql_BDport'] : '3306';


$__SQLRETOURNOMDETABLE = 'Tables_in_';
$__SQLRETOURNOMCOLONE  = 'Field';
$__DATETIME            = time();
$__RENDERPATH          = '../../render/';


try{

    $bdd = new PDO(
        'mysql:host=localhost;dbname='.$sql_DBName.';charset=utf8;port='.$sql_BDport,
        $sql_DBUser,
        $sql_DBPsw,
        array(PDO::ATTR_ERRMODE=>PDO::ERRMODE_WARNING)
    );


// ========================================================= //
/**                 STEP1 :
 *  acceder au info de la bdd pour avoir le nom des tables
 *  et des colones 
 */
// ========================================================= //


    // On recup toute les tables 
    $reqGetTable = $bdd->prepare('SHOW TABLES');
    $reqGetTable->execute();

    $sql_Tables = [];
    while($rowSqlTable = $reqGetTable->fetch(PDO::FETCH_ASSOC)) {
        $sql_Tables[] = [ 
            'TableName' => $rowSqlTable[$__SQLRETOURNOMDETABLE.$sql_DBName],
            'Colone'    => []
        ];
    }

    // var_dump($sql_Tables);


    // On recup toute les colones
    foreach ($sql_Tables as $key => $sql_TablesRow) {
        $reqGetColone = $bdd->prepare('SHOW COLUMNS FROM ' . $sql_TablesRow['TableName']);
        $reqGetColone->execute();

        while($rowSqlColone = $reqGetColone->fetch(PDO::FETCH_ASSOC)) {
            $sql_Tables[$key]['Colone'][] = $rowSqlColone[$__SQLRETOURNOMCOLONE];
        }
    }

    // var_dump($sql_Tables);


// ========================================================= //
/**                 STEP 2 :
 *  Cree les models pour chaque table avec pour chaque 
 *  => les fonctions qui vont bien pour ajouter, supprimer
 *  update... dans la base de donné
 */
// ========================================================= //

// On cree le dossier 
mkdir($__RENDERPATH. $__DATETIME);

foreach ($sql_Tables as $key => $table) {
    // on cree notre model
    $fichierModel = fopen( $__RENDERPATH . $__DATETIME . '/' . ucfirst($table['TableName']) .'.php', 'c+b');


//  ============== //
//  Debut du model //
//  ============== //

$text = 
'<?php
class '. ucfirst($table['TableName']) .' extends Model {
        ';

    foreach ($table['Colone'] as $colone) {
        $text .= 'public $'. $colone .';
        '; 
    }

    $text .= '
    public function __construct($id=null){
        parent::__construct();
        if(!is_null($id)){
            $req = $this->bdd->prepare("SELECT * FROM '. $table['TableName'] .' WHERE '.$table['Colone'][0].'=:id");
            $req->bindValue(":id", $id);
            $req->execute();
            $data = $req->fetch(PDO::FETCH_ASSOC);
            ';
    
        foreach ($table['Colone'] as $colone) {
            $text .= '$this->'. $colone .' = $data["'.$colone.'"];
            '; 
        }
    $text .= '}
        }
        
    public function create() {
        $req = $this->bdd->prepare("INSERT INTO '.$table['TableName'].' (';
        foreach ($table['Colone'] as $colone) {
            $text .= $colone. ', '; 
        }
        $text .= ') VALUE (';
        foreach ($table['Colone'] as $colone) {
            $text .= ':'. $colone. ', '; 
        }
        $text .= ')");
        ';
        foreach ($table['Colone'] as $colone) {
        $text .= '$req->bindValue(":'. $colone .'",$this->'.$colone.');
        '; 
        }
    $text .= '
        $req->execute();
        $this->'.$table['Colone'][0]. ' = $this->bdd->lastInsertId();
    }
    
    public function update() {
        $req = $this->bdd->prepare("UPDATE '.$table['TableName'].' SET ';
        foreach ($table['Colone'] as $colone) {
            $text .= $colone. ' = :'.$colone.', '; 
        }
        $text .= 'WHERE '.$table['Colone'][0].' = :id");
        ';
        foreach ($table['Colone'] as $colone) {
            $text .= '$req->bindValue(":'. $colone .'",$this->'.$colone.');
        '; 
        }
    $text .= '
        $req->execute();
    }

    public function delete() {
        $req = $this->bdd->prepare("DELETE FROM '.$table['TableName'].' WHERE '.$table['Colone'][0].' = :id");
        $req->bindValue(":id", $this->id);
        $req->execute();
    }
    
    public static function getAll(){
        $model = parent::getInstance();
        $req = $model->bdd->query("SELECT * FROM '.$table['TableName'].'");
        $livres = $req->fetchAll(PDO::FETCH_ASSOC);
        return $livres;
    }';

    $text .='
}';
//  ============ //
//  FIN du model //
//  ============ //

    fwrite($fichierModel,$text);
    fclose($fichierModel); 
}

}
catch(Exception $e){
    die('Erreur : '.$e->getMessage());
}