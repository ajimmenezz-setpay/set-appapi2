<!DOCTYPE html>
<html>
  <head>
    <title>API Conector Documentation</title>
    <meta charset="utf-8" />
    <style>
      html, body {
        margin: 0;
        padding: 0;
        height: 100%;
      }
      #redoc-container {
        height: 100vh;
      }
    </style>
  </head>
  <body>
    <div id="redoc-container">Cargando documentación...</div>

    <script src="https://cdn.redoc.ly/redoc/latest/bundles/redoc.standalone.js"></script>
    <script>
      Redoc.init('{{ url('docs/appapi2.json') }}', {}, document.getElementById('redoc-container'));
    </script>
  </body>
</html>
