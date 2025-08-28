<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Crédito aprobado - Card Cloud</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f2f4f8;
            margin: 0;
            padding: 0;
        }

        .container {
            background-color: #ffffff;
            padding: 30px;
            margin: 60px auto;
            max-width: 520px;
            border-radius: 12px;
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.06);
        }

        h2 {
            color: #2c3e50;
            font-size: 22px;
            margin-bottom: 15px;
            text-align: center;
        }

        p {
            color: #333333;
            font-size: 15px;
            line-height: 1.6;
        }

        .credentials {
            background-color: #f8f9fa;
            border-left: 5px solid #3498db;
            padding: 15px 20px;
            margin: 20px 0;
            border-radius: 8px;
        }

        .credentials p {
            margin: 5px 0;
            font-family: monospace;
            font-size: 16px;
            color: #1c1c1c;
        }

        .btn {
            display: block;
            width: fit-content;
            margin: 25px auto;
            padding: 12px 24px;
            background-color: #3498db;
            color: #fff;
            text-decoration: none;
            font-size: 15px;
            border-radius: 6px;
            font-weight: bold;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
        }

        .btn:hover {
            background-color: #2980b9;
        }

        .footer {
            font-size: 12px;
            color: #888;
            text-align: center;
            margin-top: 40px;
        }
    </style>
</head>

<body>
    <div class="container">
        <h2>¡Tu crédito ha sido aprobado!</h2>

        <p>Hola,</p>
        <p>Nos complace informarte que tu crédito ha sido autorizado en nuestra plataforma <strong>Card Cloud</strong>. A continuación encontrarás tus credenciales de acceso:</p>

        <div class="credentials">
            <p><strong>Usuario:</strong> {{ $email }}</p>
            <p><strong>Contraseña:</strong> {{ $password }}</p>
        </div>

        <p>Por razones de seguridad, te recomendamos cambiar esta contraseña al iniciar sesión por primera vez.</p>

        <a href="{{ $base_url }}" class="btn">Iniciar sesión</a>

        <p style="font-size: 13px; color: #555; text-align:center;">Si no solicitaste este acceso, puedes ignorar este mensaje.</p>

        <div class="footer">
            © 2025 Card Cloud. Todos los derechos reservados.
        </div>
    </div>
</body>
</html>
