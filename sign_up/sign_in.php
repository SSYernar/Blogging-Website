<?php 
  require "../db.php";

  $data = $_POST;

  $errors = array();


    if(isset($data['do_sign_in']))
    {
      if(trim($data['email']) == '')
        $errors[] = "Enter your Email!";
      if(trim($data['password']) == '')
        $errors[] = "Enter your Password!";
      $user = R::findOne('users', 'email = ?', array($data['email']));
      if(!$user)
        $errors[] = "User with this Email don't exist!";
      else
      {
        if (password_verify($data['password'], $user -> password))
        {
          $_SESSION['logged_user'] = $user;
          header('Location: /blog');
        }
        else
          $errors[] = "Wrong password!";
      }

    }
    if(!empty($errors))
      echo "<div style = 'color: red; font-size: 30px; text-align: center'>" . array_shift($errors) . "</div><hr>";

?>
<head>
  <meta charset="UTF-8">
  <title>Login Form</title>
  <link href='http://fonts.googleapis.com/css?family=Titillium+Web:400,300,600' rel='stylesheet' type='text/css'>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/normalize/5.0.0/normalize.min.css">
  <link rel="stylesheet" href="css/style.css">

  <style>
    .to_main
    {
      margin-top: 50px;
      margin-left: 100px;
      background-color: #FFF5D7;
      border-radius: 20px; 
      width: 200px;
      height: 70px;
      opacity: 0.5;
      transition: 1s;
    }
    .to_main:hover
    {
      opacity: 1;
    }
    .to_main:hover .to_main1
    {
      color: black;
    }
    .to_main1
    {
      display: block; 
      color: black; 
      text-align: center; 
      line-height: 70px; 
      font-size: 25px;
      text-decoration: none;
    }
    input
    {
      height: 40px;
      margin-bottom: 5px; 
      margin-top: -30px;
    }

  </style>
</head>

<body>
  <div class="to_main">
    <a class="to_main1" href="../index.php">To main page</a>
  </div>
  <div class="form">
      
      <div class="tab-content">
        <div id="login" style="display: block">   
          <h1>Welcome Back!</h1>
          
          <form action="sign_in.php" method="post">
          
            <div class="field-wrap" style="height: 40px">
            <label>
              Email Address<span class="req">*</span>
            </label>
          </div>
          <input name="email" type="email" autocomplete="off" />

          
          <div class="field-wrap" style="height: 40px">
            <label>
              Password<span class="req">*</span>
            </label>
          </div>
          <input name="password" type="password" autocomplete="off" />

          
          <!-- <p style="margin-top: 20px" class="forgot"><a style="color: #FFF5D7" href="#">Forgot Password?</a></p> -->
          <br>
          <button name="do_sign_in" class="button button-block"/>Log In</button>
          </form>
        </div>
      </div>
</div>
  <script src='http://cdnjs.cloudflare.com/ajax/libs/jquery/2.1.3/jquery.min.js'></script>
  <script src="js/index.js"></script>
</body>