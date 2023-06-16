<?php 
	require 'db.php';
	require 'navbar.php';
	$user_login = $_SESSION['logged_user']->login;
	$user_email = $_SESSION['logged_user']->email;
?>
<?php 
	$data = $_POST;
	$errors = array();
	if(isset($data['save']))
	{
		if(trim($data['old_p']) == '')
			$errors[] = 'Enter your old password!';
		if($data['new_p'] == '' || $data['r_new_p'] == '')
			$errors[] = 'Enter your new password!';
		if(trim($data['new_l'] == ''))
			$errors[] = 'Enter your new login!';
		if(password_verify($data['old_p'], $_SESSION['logged_user']->password))
		{
			if($data['new_p'] != $data['r_new_p'])
				$errors[] = 'Fill passwords fields same!';
		}
		else
			$errors[] = 'Your old password not correct!';
		if(empty($errors))
		{
			$user = R::load('users', $_SESSION['logged_user']->id );
			$user -> login = $data['new_l'];
			$user -> password = password_hash($data['new_p'], PASSWORD_DEFAULT);
			R::store($user);
			$_SESSION['logged_user'] = $user;
        	header('Location: /blog');
		}
		else
			echo "<div style = 'color: red; font-size: 30px; text-align: center; margin-top: 100px'>" . array_shift($errors) . "</div><hr>";
	}
?>
<head>  
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/0.98.2/css/materialize.min.css">
  <link href="https://fonts.googleapis.com/css?family=Lobster|Open+Sans|Archivo+Black" rel="stylesheet">
  <style>
  	body
  	{
  		zoom: 75%;
		background-color: #FFF5D7;
  	}
  	#v
  	{
  		margin-left: 1000px;
  	}
  	.waves-effect
  	{
  		display: none;
  	}
  	#info
  	{
		margin-top: 200px;
		margin-left: 100px;
		height: 300px;
		width: 1000px;
		background-color: #F1EEE0;
		border-radius: 10px;
  	}
  	#info div
  	{
  		margin-top: 30px;
  		margin-left: 50px;
  	}
  	#info img
  	{
  		border-radius: 100%;
  		margin-top: 40px;
  	}
  	#info h5
  	{
  		margin-top: 20px;
  		margin-left: 300px;
  	}
  	#update form
  	{
  		margin-left: 100px;
  		margin-top: 100px;
  	}
  	#update form label
  	{
  		font-size: 30px;
  	}
  	#update form input
  	{
		width: 500px;
		font-size: 30px;
		margin-left: 30px;
  	}
  	#update form #save
  	{
  		margin-left: 100px;
		background-color: #57AD51;
		border: none;
		border-radius: 10px;
		width: 300px;
		height: 100px;
  	}
  </style>
  <title>Profile</title>
</head>
<body>
	<div id="info">
		<div>
			<img src="avatar/ava_none.jpg" alt="avatar">
			<h5 style="margin-top: -170px">Login: <?php echo '<strong>' . $user_login . '</strong>' ?></h5>
			<h5>Email: <?php echo '<strong>' . $user_email . '</strong>' ?></h5>
			<!-- <form enctype="multipart/form-data" method="post">
	   			<p><input type="file" name="f">
	  		</form>  -->
  		</div>
	</div>
	<div id="update">
		<form action="profile.php" method="post">
			<label>Old Password: </label>
			<input style="margin-left: 145px" name="old_p" type="Password"><br>
			<label>New Password: </label>
			<input style="margin-left: 130px" type="Password" name="new_p"><br>
			<label>Repeat New Password: </label>
			<input type="Password" name="r_new_p"><br>
			<label>New Login: </label>
			<input style="margin-left: 190px" type="text" name="new_l"><br><br><br>
			<input id="save" type="Submit" value="Save Changes" name="save">
		</form>
	</div>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/0.98.2/js/materialize.min.js"></script>
	<script>
		$(document).ready(function()
		{
	      	$('.parallax').parallax();
			$(".dropdown-button").dropdown();
	    });
  	</script>
</body>