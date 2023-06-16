<?php 
	$connection = mysqli_connect('localhost', 'root', 'mysql', 'blog');

	$output = '';
	if(isset($_POST['search']))
	{
		$searchq = $_POST['search'];
		$searchq = preg_replace("#[^0-9a-z]#i", "", $searchq);
		$query = mysqli_query($connection, "SELECT * FROM blogs WHERE 'title 'LIKE '%$searchq%' OR 'text' LIKE '%$searchq%'");
		$search_count = mysqli_num_rows($query);
		if($search_count == 0)
		{
			$output = "There was no search results!";
		}
		else
		{
			while ($row = mysqli_fetch_array($query)) 
			{
				$fname = $row['first_name'];
				$lname = $row['last_name'];

				$output .= '<div>' . $fname . ' ' . $lname . '</div>';
			}
		}
	}
 ?>
<form action="index.php" method="POST">
	<input type="text" name="search" value = "<?php echo @$_POST['search']; ?>" placeholder="Search for members...">
	<input type="submit" value="Search">
</form>
<?php print("$output");
echo "<hr>" ?>