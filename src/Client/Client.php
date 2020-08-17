<?php

declare(strict_types=1);

namespace Setono\GLS\Webservice\Client;

use function Safe\sprintf;
use Setono\GLS\Webservice\Exception\ClientException;
use Setono\GLS\Webservice\Exception\ConnectionException;
use Setono\GLS\Webservice\Exception\ExceptionInterface;
use Setono\GLS\Webservice\Exception\NoResultException;
use Setono\GLS\Webservice\Exception\ParcelShopNotFoundException;
use Setono\GLS\Webservice\Exception\SoapException;
use Setono\GLS\Webservice\Model\ParcelShop;
use Setono\GLS\Webservice\Response\Response;
use SoapClient;
use SoapFault;

final class Client implements ClientInterface
{
    /** @var SoapClient */
    private $soapClient;

    public function __construct(SoapClient $soapClient)
    {
        $this->soapClient = $soapClient;
    }

    public function getAllParcelShops(string $countryCode): array
    {
        try {
            $response = $this->sendRequest('GetAllParcelShops', [
                'countryIso3166A2' => $countryCode,
            ]);

            $result = $response->getResult();
            if (null === $result) {
                throw new NoResultException($response, sprintf('There was no result for the country code %s', $countryCode));
            }

            if (!isset($result->GetAllParcelShopsResult->PakkeshopData)) {
                throw new NoResultException($response, sprintf('There was no result for the country code %s', $countryCode));
            }

            $parcelShops = [];
            foreach ($result->GetAllParcelShopsResult->PakkeshopData as $parcelShop) {
                $parcelShops[] = ParcelShop::createFromStdClass($parcelShop);
            }

            return $parcelShops;
        } catch (ClientException $e) {
            if (!$e->getResponse()->isOk() || $e->getResponse()->getResult() === null) {
                return [];
            }

            throw $e;
        }
    }

    public function getOneParcelShop(string $parcelShopNumber): ParcelShop
    {
        try {
            $response = $this->sendRequest('GetOneParcelShop', ['ParcelShopNumber' => $parcelShopNumber]);
            $result = $response->getResult();

            if (null === $result || !isset($result->GetOneParcelShopResult)) {
                throw new ParcelShopNotFoundException($parcelShopNumber);
            }

            return ParcelShop::createFromStdClass($result->GetOneParcelShopResult);
        } catch (ClientException $e) {
            if ($e->getResponse()->is404() || $e->getResponse()->getResult() === null) {
                throw new ParcelShopNotFoundException($parcelShopNumber);
            }

            throw $e;
        }
    }

    public function getParcelShopDropPoint(string $street, string $zipCode, string $countryCode, int $amount): array
    {
        try {
            $response = $this->sendRequest('GetParcelShopDropPoint', [
                'street' => $street,
                'zipcode' => $zipCode,
                'countryIso3166A2' => $countryCode,
                'Amount' => $amount,
            ]);

            $result = $response->getResult();
            if (null === $result) {
                return [];
            }

            $parcelShops = [];
            foreach ($result->GetParcelShopDropPointResult->parcelshops->PakkeshopData as $parcelShop) {
                $parcelShops[] = ParcelShop::createFromStdClass($parcelShop);
            }

            return $parcelShops;
        } catch (ClientException $e) {
            if (!$e->getResponse()->isOk() || $e->getResponse()->getResult() === null) {
                return [];
            }

            throw $e;
        }
    }

    public function getParcelShopsInZipCode(string $zipCode, string $countryCode): array
    {
        try {
            $response = $this->sendRequest('GetParcelShopsInZipCode', [
                'zipcode' => $zipCode,
                'countryIso3166A2' => $countryCode,
            ]);

            $result = $response->getResult();
            if (null === $result) {
                return [];
            }

            $parcelShops = [];
            foreach ($result->GetParcelShopsInZipcodeResult->PakkeshopData as $parcelShop) {
                $parcelShops[] = ParcelShop::createFromStdClass($parcelShop);
            }

            return $parcelShops;
        } catch (ClientException $e) {
            if (!$e->getResponse()->isOk() || $e->getResponse()->getResult() === null) {
                return [];
            }

            throw $e;
        }
    }

    public function searchNearestParcelShops(string $street, string $zipCode, string $countryCode, int $amount = 10): array
    {
        try {
            $response = $this->sendRequest('SearchNearestParcelShops', [
                'street' => $street,
                'zipcode' => $zipCode,
                'countryIso3166A2' => $countryCode,
                'Amount' => $amount,
            ]);

            $result = $response->getResult();
            if (null === $result) {
                return [];
            }

            $parcelShops = [];
            foreach ($result->SearchNearestParcelShopsResult->parcelshops->PakkeshopData as $parcelShop) {
                $parcelShops[] = ParcelShop::createFromStdClass($parcelShop);
            }

            return $parcelShops;
        } catch (ClientException $e) {
            if (!$e->getResponse()->isOk() || $e->getResponse()->getResult() === null) {
                return [];
            }

            throw $e;
        }
    }

    private function sendRequest(string $method, array $arguments = []): Response
    {
        $result = null;

        try {
            $result = $this->soapClient->{$method}($arguments);

            return new Response($this->soapClient->__getLastResponseHeaders(), $this->soapClient->__getLastResponse(), $result);
        } catch (SoapFault $soapFault) {
            throw $this->parseException($soapFault);
        }
    }

    private function parseException(SoapFault $soapFault): ExceptionInterface
    {
        $message = $soapFault->getMessage();

        if ('Could not connect to host' === $message) {
            return new ConnectionException($soapFault);
        }

        /**
         * The response are null if no response was fetched (i.e. no connection)
         *
         * @var string|null
         */
        $responseHeaders = $this->soapClient->__getLastResponseHeaders();

        if ($responseHeaders !== null) {
            return new ClientException(
                $soapFault, new Response($responseHeaders, $this->soapClient->__getLastResponse(), null)
            );
        }

        return new SoapException($soapFault, $soapFault->getMessage());
    }
}
