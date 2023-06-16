<?php 
	require 'db.php';
	require 'navbar.php';
	header('Content-type: text/html; charset=utf-8');
?>
<head>
<style>
	body
	{
		zoom: 75%;
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
	#blog a
	{
		font-size: 30px;
		margin-left: 1350px;
		margin-top: -100px;
	}
	#blog h4
	{
		text-align: center;
	}
</style>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/0.98.2/css/materialize.min.css">
  <link href="https://fonts.googleapis.com/css?family=Lobster|Open+Sans|Archivo+Black" rel="stylesheet">
  <link rel="stylesheet" type="text/css" href="style.css">
  <title>Ramina Nr</title>
  <link rel="icon" 
      type="image/png" 
      href="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTFhCmFNYm-gdkPnbVmRuKAInrTV4t3v8A29NegX0TQWQslZERl">
</head>

<body>

<div style="display: block; margin-top: 100px">
  <div class="parallax-container">
    <div class="parallax"><img src="https://static.wixstatic.com/media/c8381ce0e7a94ad09ceaada2fc64081d.jpg/v1/fill/w_1617,h_1276,al_c,q_90/c8381ce0e7a94ad09ceaada2fc64081d.webp"></div>
  </div>
    <div class="categories">
		<p><a href="#">Lifestyle</a></p>
		<p><a href="#">Health</a></p>
		<p><a href="#">Fashion</a></p>
		<p id="l"><a href="#">Travel</a></p>
	</div>
	
	<div id="about_me">
		<p class="fadeInUp" style="font-family: 'Archivo Black', sans-serif; font-size: 30px; text-align: center; margin-top: 500px;">ABOUT ME</p>
		<p class="fadeInUp" style="text-align: center; font-family: 'Open Sans', sans-serif; font-size: 16px; margin-top: -25px;letter-spacing: 5px; opacity: 0.6;
	">Blogger. Traveler. Dancer. Photographer.</p>
		<p class="fadeInUp" style="text-align: center; font-family: 'Open Sans', sans-serif; font-size: 15px; margin-top: 35px">Время представить себя Вам. Меня зовут Рамина. Люблю танцевать, печь, делать <br>красивые снимки, находить полезные статьи и делиться ими с Вами.</p><br><br>
		<div class="images">
			<img src="images/1.jpg" style="width: 304px; display: block; margin-left: 330px">
			<img src="images/2.jpg" style="width: 350px; display: block; margin: auto; margin-top: -430px">
	    	<img src="images/3.jpg" style="width: 349px; display: block; margin: auto; margin-top: 30px">
	    	<img src="images/4.jpg" style="width: 308px; display: block; margin-left: 1060px;margin-top: -620px">
		</div>
		<br><br><br><br><br><br><br><br><br><br>
	</div>
	<div class="parallax-container">
    	<div class="parallax"><img src="images/5.jpg" width="100%" style="margin-top: -1000px; float:left;"></div>
    	<div id="lo"></div>
  	</div>
  	<div id="blog" style="height: 1150px">
  		<h4>Самые новые посты</h4>
	  	<?php 
	  		for($i=1;$i<=8;$i++)
			{
				$total_count_of_posts = R::count('blogs');
				$txt = R::findOne( 'blogs', ' id LIKE ? ', [$total_count_of_posts-$i+1] );
				echo "<div id='first_blog' class='blog" . $i . "' title = '" . $txt['title'] . "'>
		  		<div id='img_blog'></div>
		  		<p class='p" . $i . "'>" . $txt['title'] . "</p>
				</div>";
			}
	  	?>
	  	<a href="blogs.php">Смотреть все посты ></a>
	</div>
	<br><br><br>
    <?php require 'footer.php' ?>
	<script>
		$(document).ready(function()
		{
	      	$('.parallax').parallax();
			$(".dropdown-button").dropdown();
			$("#q").click(function() 
			{   
				$('html,body').animate({scrollTop: $("#l").offset().top},'slow');
			});
			$("#w").click(function() 
			{
				$('html,body').animate({scrollTop: $("#lo").offset().top},'slow');
			});
			$("#e").click(function() 
			{
				$('html,body').animate({scrollTop: $("#instagram").offset().top},'slow');
			});
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
			<?php
				for($i=1;$i<=8;$i++)
				{
					echo"
					$('.blog" . $i . "').click(function() 
					{ 
			    		document.location = 'blog.php?blog_id=" . ($total_count_of_posts-$i+1) . "';
					});";
					echo "document.querySelector('.p" . $i . "').innerText = truncateText('.p" . $i . "',25);";
				}
			?>
    	});
  	</script>
</body>
</html>