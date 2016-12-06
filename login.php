<?php
defined('DS') OR die('No direct access allowed.');

/*$users = array(
                'Karl'=>'Olofsson',
                'Claus'=>'Fasseland',
                'Marthe'=>'Eide',
                'Ummear'=>'Khan',
                'FredrikO'=>'Oterholt',
                'Fredrik'=>'Larsen',
                'Kjell Arne'=>'Arvesen',
                'Daniel'=>'Martinsen',
                );*/

if(isset($_GET['logout'])) {
    $_SESSION['username'] = '';
    header('Location:  ' . $_SERVER['PHP_SELF']);
}

if(isset($_POST['username'])) {
    if($users[$_POST['username']] !== NULL && $users[$_POST['username']] == $_POST['password']) {
        $_SESSION['username'] = $_POST['username'];
        header('Location:  '. $_SERVER['PHP_SELF']); //
    }else {
        //invalid login
        echo "<p>error logging in</p>";
    }
}

$template = '<!--<script src="https://apis.google.com/js/platform.js" async defer></script>
<div class="g-signin2" data-onsuccess="onSignIn"></div>-->
<div class="container text-center">
<form method="post" action="'.SELF.'" class="espace-lg form-inline">
  <div class="page-header"><h1 style="color: #FFF;">Solidsquares Julekalender</h1></div>
  <p><label for="username">Bruker</label> <input type="text" id="username" name="username" value="" class="form-control input-lg"/></p>
  <p><label for="password">Passord</label> <input type="password" id="password" name="password" value="" class="form-control input-lg"/></p>
  <!--<input type="checkbox" name="remember">Husk meg!-->
  <p><input type="submit" name="submit" value="Logg inn" class="btn btn-default btn-lg tip"/></p>
  </form>
</div>';
?>