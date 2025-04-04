<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Código de verificación</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }

        .container {
            background-color: #ffffff;
            padding: 20px;
            margin: 40px auto;
            max-width: 500px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .code {
            font-size: 28px;
            font-weight: bold;
            letter-spacing: 6px;
            background: #f0f0f0;
            padding: 10px 20px;
            text-align: center;
            margin: 20px 0;
            border-radius: 6px;
        }

        .footer {
            font-size: 12px;
            color: #999999;
            text-align: center;
            margin-top: 30px;
        }
    </style>
</head>

<body>
    <div class="container">
        <h2>Validación de Cuenta</h2>
        <p>Hola,</p>
        <p>Para completar la validación de tu cuenta, por favor introduce el siguiente código:</p>
        <div class="code">{{ $code }}</div>
        <p>Este código tiene una validez limitada. Si tú no solicitaste este correo, puedes ignorarlo.</p>
        <p>Gracias por confiar en nosotros.</p>
        <div class="footer">
            © 2025 Todos los derechos reservados.
        </div>
    </div>
</body>

</html>
