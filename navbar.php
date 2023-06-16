<?php if(isset($_SESSION['logged_user'])) : $user_login = $_SESSION['logged_user']->login; ?>
  <ul id="dropdown1" class="dropdown-content" style="margin-left: 470px">
    <li><a><?php echo $user_login ?></a></li>
    <li class="divider"></li>
    <?php if($_SESSION['logged_user']->login == 'admin') 
    {
      echo "<li><a href='admin_page.php'>Admin Page</a></li>";
    }
    ?>
    <li><a href="profile.php">Profile</a></li>
    <li class="divider"></li>
    <li><a href="sign_up/sign_out.php">Sign Out</a></li>
  </ul>
<nav style = "position: fixed; z-index: 1; top: 0px; background-color: #FFF5D7; height: 100px;">
    <div class="nav-wrapper">
      <a style="color: black" href="index.php" class="brand-logo"><i><b style = "font-size: 40px; margin-left: 100px; line-height: 100px
            ">RAMINA NR</b></i></a>
      <ul id="nav-mobile" class="navb">
        <li id="q"><a class="waves-effect waves-light" style="color: black; line-height: 100px">Обо мне</a></li>
        <li id="w"><a class="waves-effect waves-light" style="color: black; line-height: 100px">Блог</a></li>
        <li id="e"><a class="waves-effect waves-light" style="color: black; line-height: 100px">Контакты</a></li>
        <li id="v"><a style="height: 100px" class="dropdown-button" data-activates="dropdown1"><img src="avatar/ava_none.jpg" style="border-radius: 100%; width: 70px; margin-top: 15px; margin-left: 500px"></a></li>
      </ul>
    </div>
</nav>


<?php else : ?>
<nav style = "position: fixed; z-index: 1; top: 0px; background-color: #FFF5D7; height: 100px;">
    <div class="nav-wrapper">
      <a style="color: black" href="index.php" class="brand-logo"><i><b style = "font-size: 40px; margin-left: 100px; line-height: 100px
            ">RAMINA NR</b></i></a>
      <ul id="nav-mobile" class="navb">
        <li id="q"><a class="waves-effect waves-light" style="color: black; line-height: 100px">Обо мне</a></li>
        <li id="w"><a class="waves-effect waves-light" style="color: black; line-height: 100px">Блог</a></li>
        <li id="e"><a class="waves-effect waves-light" style="color: black; line-height: 100px">Контакты</a></li>
        <div id="signIn_logIn">
          <li style="line-height: 100px; margin-left: 370px"><a class="btn-large pulse" href="sign_up/sign_up.php">Sign Up</a></li>
          <li><a style="color: black; line-height: 100px; margin-left: 50px;" href="sign_up/sign_in.php" class="LI">Log In</a></li>
        </div>
      </ul>
    </div>
</nav>
<?php endif; ?>
  <script type="text/javascript" src = "jquery-3.2.0.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/0.98.2/js/materialize.min.js"></script>
<script>
  $(document).ready(function()
    {
      $(".dropdown-button").dropdown();
    });
</script>