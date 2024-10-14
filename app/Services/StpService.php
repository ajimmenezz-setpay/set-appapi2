<?php

namespace App\Services;

use GuzzleHttp\Client;


class StpService
{
    private $client;

    public function __construct($url)
    {
        $this->client = new Client([
            'base_uri' => $url,
            'timeout' => 2.0,
        ]);
    }

    public static function getBalance($url, $key, $company, $account)
    {
        $service = new StpService($url);
        $body = json_encode([
            "app" => "getBalance",
            "keys" => $key,
            "company" => $company,
            "account" => $account
        ]);

        $response = $service->client->request('POST', '', [
            'body' => $body
        ]);

        return json_decode($response->getBody()->getContents());
    }

    public static function getConciliation($url, $key, $company)
    {
        $service = new StpService($url);
        $body = json_encode([
            "app" => "getConciliation",
            "keys" => $key,
            "company" => $company
        ]);

        $response = $service->client->request('POST', '', [
            'body' => $body
        ]);

        return json_decode($response->getBody()->getContents());
    }

    public static function getCollection($url, $key, $company, $date)
    {
        $service = new StpService($url);
        $body = json_encode([
            "app" => "getCollectionSTP",
            "keys" => $key,
            "company" => $company,
            "fechaOperacion" => $date
        ]);

        $response = $service->client->request('POST', '', [
            'body' => $body
        ]);

        return json_decode($response->getBody()->getContents());
    }

    public static function speiOut($url, $key, $company, $amount, $traceKey, $concept, $originAccount, $originName, $originRfc, $beneficiaryAccount, $beneficiaryName, $reference, $institution, $beneficiaryRfc, $originAccountType, $beneficiaryInstitution, $beneficiaryAccountType)
    {

        $service = new StpService($url);
        $body = json_encode([
            "app" => "getOrder",
            "keys" => $key,
            "Monto" => $amount,
            "Empresa" => $company,
            "TipoPago" => 1,
            "ClaveRastreo" => $traceKey,
            "ConceptoPago" => $concept,
            "CuentaOrdenante" => $originAccount,
            "NombreOrdenante" => $originName,
            "RfcCurpOrdenante" => $originRfc,
            "CuentaBeneficiario" => $beneficiaryAccount,
            "NombreBeneficiario" => $beneficiaryName,
            "ReferenciaNumerica" => $reference,
            "InstitucionOperante" => $institution,
            "RfcCurpBeneficiario" => $beneficiaryRfc,
            "TipoCuentaOrdenante" => $originAccountType,
            "InstitucionContraparte" => $beneficiaryInstitution,
            "TipoCuentaBeneficiario" => $beneficiaryAccountType
        ]);

        $response = $service->client->request('POST', '', [
            'body' => $body
        ]);

        return json_decode($response->getBody()->getContents());
    }
}
