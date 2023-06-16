<?php
	require 'db.php';
	$blog_idd=$_GET['blog_id']
?>
<head>
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/0.98.2/css/materialize.min.css">
	<link href="https://fonts.googleapis.com/css?family=Lobster|Open+Sans|Archivo+Black" rel="stylesheet">
	<script type="text/javascript" src = "jquery-3.2.0.min.js"></script>
	<?php require 'navbar.php'; ?>
	<title><?php echo R::findOne('blogs', 'id = ?', [$blog_idd])->title; ?></title>
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
	  	#image_of_post
	  	{
	  		width: 100%;
	  		height: 100px;
	  		background-color: red;
	  	}
	  	#title_of_post
	  	{
	  		text-align: center;
	  		margin-left: 100px;
	  	}
	  	#text_of_post
	  	{
	  		font-size: 30px;
	  		margin-left: 50px;
	  		margin-right: 50px;
			white-space: pre-wrap;
	  	}
	  	#date
	  	{
	  		font-size: 20px;
	  		margin-left: 1200px;
	  	}
	  	#signIn_logIn
		{
			margin-left: 950px;
		}
		#like_btn
		{
			cursor: pointer;
			width: 50px;
			margin-left: 30px;
		}
	</style>
</head>
<body>
	<br><br><br><br>
	<!-- <h1><?php //echo $_GET['blog_id']; ?></h1> -->
	<!-- <div id="image_of_post">
		<img src="images/5.jpg">
	</div> -->
	<div class="parallax-container">
    	<div class="parallax"><img src="images/5.jpg"></div>
  	</div>
	<h1 id="title_of_post">
		<?php
			echo R::findOne('blogs', 'id = ?', [$blog_idd])->title;
		?>
	</h1>
	<p id="text_of_post">
		<?php
			echo R::findOne('blogs', 'id = ?', [$blog_idd])->text;
		?>
	</p>
	<strong id="date">
		Опубликовано: 
		<?php
			echo R::findOne('blogs', 'id = ?', [$blog_idd])->date;
		?>
	</strong>
	<script>
		$(document).ready(function()
		{
	      	$('.parallax').parallax();
	    });
	    function changeImage()
	    {
	    	if (document.getElementById("like_btn").src == "images/l1.png") 
        	{
            	document.getElementById("like_btn").src = "images/l2.png";
            	// <?php
            	// 	$book = R::findOne('post_like', 'title_post = ? AND user_post = ?', array([$blog_idd]->title, $_SESSION['logged_user']->login));
            	// 	R::trash($book); //[[$blog_idd]->title], [$_SESSION['logged_user']->login]
            	// ?>
        	}
        	else 
        	{
            	document.getElementById("like_btn").src = "images/l1.png";
            	<?php 
            		$book = R::dispense('post_like');
            		$book -> title_post = [$blog_idd]->title;
            		$book -> user_post = $_SESSION['logged_user']->login;
            		R::store($book);
            	 ?>
        	}
	    }
	</script>
	<br><br><br>
	<?php if(isset($_SESSION['logged_user'])) :
		if (R::findOne('post_like', 'title_post = ?', [[$blog_idd]->title]) && R::findOne('post_like', 'user_post = ?', [$_SESSION['logged_user']->login])) 
			echo "<img id='like_btn' src='images/l1.png' onclick='changeImage()' alt='like'>";
		else
			echo "<img id='like_btn' src='images/l2.png' onclick='changeImage()' alt='like'>";
		?>

		<?php
	?>
	<?php else : ?>
		<img id="like_btn" src="images/l2.png" onclick="alert('To like and comment posts you should to Log In into your account!')" alt="like">
	<?php endif; ?>
	<!-- <?php
		// $title_post = R::findOne('blogs', 'id = ?', [$blog_idd])->title;
		// $user_login = $_SESSION['logged_user']->login;
		// $book = R::findOne( 'post_like', 'title_post = ?', 'user_post = ?', [$title_post], [$user_login] );
		// if($book)
		// {
		// 	?><img src="like1.png" onclick="
		// 	this.src='like.png';
		// 	<?php //R::trash( $book ) ?>
		// 	location.reload();
		// 	"><?php
		// }
		// else
		// {
		// 	?><img src="like.png" onclick="
		// 	this.src='like1.png';
		// 	<?php
		// 	$new_like_blog = R::dispense( 'post_like' );
		// 	$new_like_blog->title_post = $title_post;
		// 	$new_like_blog->user_post = $user_login;
		// 	R::store( $new_like_blog );
		// 	?>
		// 	location.reload();
		// 	"><?php
		// }
	?> -->
</body>




