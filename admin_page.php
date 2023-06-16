<?php 
	require 'db.php';
?>
<?php if(isset($_SESSION['logged_user']) && $_SESSION['logged_user']->login == 'admin') :
	$data = $_POST;
	$errors = array();
	if(isset($data['add']))
	{
		if(trim($data['title']) == '')
			$errors[] = 'Enter Title!';
		if(trim($data['text']) == '')
			$errors[] = 'Enter Text!';
		if(empty($errors))
		{
			$post = R::dispense('blogs');
	        $post -> title = $data['title'];
	        $post -> text = $data['text'];
	        $post -> img = '0';
	        $post -> date = date("l, j F Y");
	        R::store($post);
        	header('Location: /blog/admin_page.php');
		}
		else
			echo "<div style = 'color: red; font-size: 30px; text-align: center; margin-top: 100px'>" . array_shift($errors) . "</div><hr>";
	}
 ?>
<head>
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/0.98.2/css/materialize.min.css">
	<link href="https://fonts.googleapis.com/css?family=Lobster|Open+Sans|Archivo+Black" rel="stylesheet">
	<script type="text/javascript" src = "jquery-3.2.0.min.js"></script>
	<?php 	require 'navbar.php'; ?>
	<title>Admin Page</title>
	<style>
		body
	  	{
	  		zoom: 75%;
			background-color: #FFF5D7;
	  	}
		.waves-effect
	  	{
	  		display: none;
	  	}
	  	#v
	  	{
	  		margin-left: 1000px;
	  	}
	  	label
	  	{
	  		margin-left: 100px;
	  		font-size: 50px;
	  		color: black;
	  	}
	  	#add
	  	{
	  		font-size: 30px;
	  		margin-left: 100px;
			background-color: #57AD51;
			border: none;
			border-radius: 10px;
			width: 300px;
			height: 100px;
			margin-left: 300px;
	  	}
	</style>
</head>
<body>
	<br><br><br><br><br>
	<form enctype="multipart/form-data" action="admin_page.php" method="POST">
		<label>Title of post: </label>
		<input style="margin-top: 50px;width: 500px;font-size: 50px;margin-left: 30px;" name="title" type="text" autocomplete="off">
		<br><br><br>
		<label>Upload image for post</label>
		<input style="margin-top: 50px;width: 500px;margin-left: 30px;" type="file">
		<br><br><br>
		<label>Text: </label>
		<textarea style="width: 1000px; height: 500px; font-size: 30px;" name="text"></textarea><br><br><br>
		<input id="add" type="Submit" value="Add Post" name="add">
	</form>
</body>
<?php else : ?>
<?php endif; ?>