<?php
	require 'db.php';
	header('Content-type: text/html; charset=utf-8');
	$data = $_POST;
	if(isset($data['submit']))
	{
		$selected_val = $data['option'];
	}
 ?>
 <head>
	 <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/0.98.2/css/materialize.min.css">
	 <link href="https://fonts.googleapis.com/css?family=Lobster|Open+Sans|Archivo+Black" rel="stylesheet">
	 <script type="text/javascript" src = "jquery-3.2.0.min.js"></script>
	 <?php require 'navbar.php'; ?>
	 <title>All Posts</title>
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
	  	#first_blog
		{
			display: inline-block;
			width: 300px;
			height: 500px;
			margin-top: 50px;
			margin-left: 90px;
			cursor: pointer;
		}
		#img_blog
		{
			border-radius: 100%;
			background-color: red;
			margin-left: 25px;
			width: 250px;
			height: 250px;
		}
		#first_blog p
		{
			text-align: center;
			font-size: 30px;
		}
		select
		{
			display: block;
			width: 200px;
			height: 30px;
		}
		#inOneLine
		{
			display: inline-block;
		}
		form label
		{
			margin-left: 1000px;
			font-size: 30px;
			color: black;
		}
		#signIn_logIn
		{
			margin-left: 950px;
		}
	 </style>
</head>
<body>
	<script>
			function truncateText(selector, maxLength) 
			{
			    var element = document.querySelector(selector),
			        truncated = element.innerText;

			    if (truncated.length > maxLength) 
			    {
			        truncated = truncated.substr(0,maxLength) + '...';
			    }
			    return truncated;
			}
	</script>
	<br><br><br><!-- <br><br>
	<form method="POST" action="blogs.php">
		<label>Sort by </label>
		<select id="inOneLine" name="option">
			<option>Default</option>
			<option>Time</option>
		  	<option>Most readable</option>
		  	<option>Quantity of likes</option>
		</select>
		<input id="inOneLine" type="submit" value="Sort" name="submit" />
	</form> -->
	<?php
		echo "<br>";
		for($i=1;$i<=R::count('blogs');$i++)
		{
			$total_count_of_posts = R::count('blogs');
			$txt = R::findOne( 'blogs', ' id LIKE ? ', [$total_count_of_posts-$i+1] );
			echo "<div id='first_blog' class='blog" . $i . "' title = '" . $txt['title'] . "'>
	  		<div id='img_blog'></div>
	  		<p class='p" . $i . "'>" . $txt['title'] . "</p>
	  		</div>";
		}
	?>
	<script>
		<?php
			for($i=1;$i<=R::count('blogs');$i++)
			{
				echo"
					$('.blog" . $i . "').click(function() 
					{ 
			    		document.location = 'blog.php?blog_id=" . ($total_count_of_posts-$i+1) . "';
					});";
					echo "document.querySelector('.p" . $i . "').innerText = truncateText('.p" . $i . "',25);";
			}
		?>
	</script>
	<br>
</body>