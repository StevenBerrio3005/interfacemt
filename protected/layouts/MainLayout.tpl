<html>
  
    <com:THead>
    <meta charset="utf-8">
    <title>Bootstrap, from Twitter</title>
    <style type="text/css">
      body {
        padding-top: 60px;
        padding-bottom: 40px;
      }
      .sidebar-nav {
        padding: 9px 0;
      }

      @media (max-width: 980px) {
        /* Enable use of floated navbar text */
        .navbar-text.pull-right {
          float: none;
          padding-left: 5px;
          padding-right: 5px;
        }
      }
    </style>
    <link rel="stylesheet" href="https://netdna.bootstrapcdn.com/twitter-bootstrap/2.3.1/css/bootstrap-combined.min.css">        
    </com:THead>
 
  
  <body>
    <div class="navbar navbar-inverse navbar-fixed-top">
      <div class="navbar-inner">
        <div class="container-fluid">
          <button type="button" class="btn btn-navbar" data-toggle="collapse" data-target=".nav-collapse">
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
          </button>
          <a class="brand" href="#">Interface MT 1.0</a>
          <div class="nav-collapse collapse">
            <p class="navbar-text pull-right">Logged in as
              <a href="#" class="navbar-link">Username</a>
            </p>
            <ul class="nav">
              <li class="active">
                <a href="#">Inicio</a>
              </li>
              <li>
                <a href="">Acerca de</a>
              </li>
              <li>
                <a href="">Contactenos</a>
              </li>
            </ul>
          </div>
          <!--/.nav-collapse -->
        </div>
      </div>
    </div>
    <div class="container-fluid">
      <div class="row-fluid">
        <div class="span3">
          <div class="well sidebar-nav">
            <ul class="nav nav-list">
              <li class="nav-header">Herramientas</li>
              <li><com:THyperLink  Text="Interface despachos" NavigateUrl="?page=herramientas.Despachos"/></li>                            
              <li class="nav-header">Control</li>
              <li>
                <li><com:THyperLink  Text="Cumplir despachos" NavigateUrl="?page=herramientas.CumplirDespachos"/></li>              
              </li>
            </ul>
          </div>
          <!--/.well -->
        </div>
          
        <!--/span-->
        <div class="span9">
            <com:TContentPlaceHolder ID="Main"/>
          <!--/row-->
        </div>
        <!--/span-->
      </div>
      <!--/row-->
      <hr>
      <footer>
        <p>&copy; Company 2013</p>
      </footer>
    </div>
    <!--/.fluid-container-->
  </body>

</html>