<?php

namespace App\Http\Controllers\Stp;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ErrorRegisterOrder extends Controller
{
    public static function error($code)
    {

        switch ($code) {
            case 0:
                return "Otros";
                break;
            case 1:
                return "Dato Obligatorio";
                break;
            case 2:
                return "Dato No Catalogado";
                break;
            case 3:
                return "La cuenta No Pertenece a la Empresa";
                break;
            case 4:
                return "Cuenta invalida";
                break;
            case 5:
                return "Dato Duplicado";
                break;
            case 6:
                return "Cuenta No Asociada";
                break;
            case 7:
                return "Cuenta No Habilitada";
                break;
            case 8:
                return "Rfc_Curp Inválido";
                break;
            case -1:
                return "Clave de rastreo duplicada";
                break;
            case -2:
                return "Orden duplicada";
                break;
            case -3:
                return "La clave no existe en el catalogo de usuario";
                break;
            case -5:
                return "Dato obligatorio {institución contraparte}";
                break;
            case -6:
                return "Empresa_Invalida O Institucion_Operante_Invalida";
                break;
            case -9:
                return "Institucion_Invalida";
                break;
            case -10:
                return "Medio_Entrega_Invalido";
                break;
            case -11:
                return "El tipo de cuenta es invalido";
                break;
            case -12:
                return "Tipo_Operacion_Invalida";
                break;
            case -13:
                return "Tipo_Pago_Invalida";
                break;
            case -14:
                return "El usuario es invalido";
                break;
            case -16:
                return "Fecha_Operacion_Invalida";
                break;
            case -17:
                return "No se pudo determinar un usuario para asociar a la orden";
                break;
            case -18:
                return "La institución operante no está asociada al usuario";
                break;
            case -20:
                return "Monto_Invalido";
                break;
            case -21:
                return "Digito_Verificador_Invalido";
                break;
            case -22:
                return "Institucion_No_Coincide_En_Clabe";
                break;
            case -23:
                return "Longitud_Clabe_Incorrecta";
                break;
            case -26:
                return "Clave de rastreo invalida";
                break;
            case -30:
                return "Enlace Financiero en modo consultas";
                break;
            case -34:
                return "Valor inválido. Se aceptan caracteres a-z,A-Z,0-9";
                break;
            case -200:
                return "Se rechaza por PLD";
                break;
            default:
                return "Error no catalogado";
                break;
        }
    }
}
