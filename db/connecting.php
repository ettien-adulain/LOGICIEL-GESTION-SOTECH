<?php 
$host="localhost";
$user="root";
$pwd="";
$bd="bdd_apple";
try{
    $cnx=new PDO('mysql:host='.$host.';dbname='.$bd.'',$user,$pwd);
}catch(exception $e){
    die ("Echec de connexion à la base de données".$e->getMessage());
}
?>