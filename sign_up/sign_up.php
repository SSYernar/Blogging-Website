<?php 
  require "../db.php";

  $data = $_POST;

  $errors = array();

    if(isset($data['do_sign_up']))
    {
      if(trim($data['login']) == '')
        $errors[] = "Enter your Login!";
      if(trim($data['email']) == '')
        $errors[] = "Enter your Email!";
      if ($data['password1'] == '' || $data['password2'] == '')
        $errors[] = "Enter your password!";
      if($data['password1'] != $data['password2'])
        $errors[] = "Fill passwords fields same!";
      if(R::count('users', 'email = ?', array($data['email'])) != 0)
        $errors[] = "User with this email is already registered!";
      if(R::count('users', 'login = ?', array($data['login'])) != 0)
        $errors[] = "User with this login is already registered!";
      if(empty($errors))
      {
        $user = R::dispense('users');
        $user -> login = $data['login'];
        $user -> email = $data['email'];
        $user -> password = password_hash($data['password1'], PASSWORD_DEFAULT);
        R::store($user);
        $_SESSION['logged_user'] = $user;
        header('Location: /blog');
      }
      else
        echo "<div style = 'color: red; font-size: 30px; text-align: center'>" . array_shift($errors) . "</div><hr>";
      }

    
?>
<head>
  <meta charset="UTF-8">
  <title>Sign-Up Form</title>
  <link href='http://fonts.googleapis.com/css?family=Titillium+Web:400,300,600' rel='stylesheet' type='text/css'>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/normalize/5.0.0/normalize.min.css">
  <link rel="stylesheet" href="css/style.css">

  <style>
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
    <a class="to_main1" style="text-decoration: none" href="../index.php">To main page</a>
  </div>
  <div class="form">
      
      <div class="tab-content">
        <div id="signup" style="display: block">   
          <h1>Sign Up for Free</h1>
          <div style="width: 200px; height: 30px"></div>

          <form action="sign_up.php" method="post">
          
            <div class="field-wrap" style="height: 40px">
              <label>
                Login<span class="req">*</span>
              </label>
            </div>
            <input name="login" type="text" autocomplete="off" />

          <div class="field-wrap" style="height: 40px">
            <label>
              Email Address<span class="req">*</span>
            </label>
          </div>
          <input name="email" type="email" autocomplete="off" />

          
          <div class="field-wrap" style="height: 40px">
            <label>
              Set A Password<span class="req">*</span>
            </label>
          </div>
          <input name="password1" type="password" autocomplete="off" />

          <div class="field-wrap" style="height: 40px">
            <label>
              Repeat Password<span class="req">*</span>
            </label>
          </div>
          <input name="password2" type="password" autocomplete="off" />

          
          <button style="margin-top: 30px" name="do_sign_up" type="submit" class="button button-block"/>Get Started</button>
          </form>
        </div>
</div>
  <script src='http://cdnjs.cloudflare.com/ajax/libs/jquery/2.1.3/jquery.min.js'></script>
  <script src="js/index.js"></script>
</body>