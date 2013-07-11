<?php

namespace Namshi\Innovate;

use Guzzle\Service\Client as BaseClient;
use Namshi\Innovate\Request\Factory as RequestFactory;
use Namshi\Innovate\Payment\Transaction;
use Namshi\Innovate\Payment\Card;
use Namshi\Innovate\Payment\BillingInformation;
use Namshi\Innovate\Payment\Browser;
use Namshi\Innovate\Exception\AuthFailed;
use SimpleXMLElement;
use Namshi\Innovate\Http\Response\Redirect;
use Symfony\Component\HttpFoundation\Response;
use Exception;

/**
 * HTTP client tied to the Innovate API.
 */
class Client extends BaseClient
{
    const INNOVATE_URL          = "https://secure.innovatepayments.com/gateway/remote.xml";
    const INNOVATE_MPI_URL      = "https://secure.innovatepayments.com/gateway/remote_mpi.xml";
    const RESULT_ERROR_STATUS   = 'E';
    const ERROR_RESPONSE        = 400;
    const SUCCESS_RESPONSE      = 200;

    protected $storeId;
    protected $key;
    protected $transaction;
    protected $card;
    protected $billingInformation;
    protected $browser;

    /**
     * Constructor
     *
     * @param type $storeId
     * @param type $key
     * @param \Namshi\Innovate\Payment\Transaction $transaction
     * @param string $baseUrl
     * @param array $config
     */
    public function __construct($storeId, $key, $baseUrl = '', $config = null)
    {
        parent::__construct($baseUrl, $config);

        $this->setStoreId($storeId);
        $this->setKey($key);
        $this->setRequestFactory(RequestFactory::getInstance());
    }

    /**
     * Sends a request to the Innovate API with all the informations about the
     * payment to be performed.
     *
     * @return Request
     */
    public function performPayment(Transaction $transaction, Card $card, BillingInformation $billing, Browser $browser)
    {
        try {
            $this->setTransaction($transaction);
            $this->setCard($card);
            $this->setBillingInformation($billing);
            $this->setBrowser($browser);

            $authorization  = $this->authorizeMpiRequest();
            $mpi            = $authorization->xml()->mpi;

            if (empty($mpi->acsurl)) {
                return $this->authorizeRemoteRequest(array($mpi->session));
            } else {
                return new Redirect($mpi->acsurl, $mpi->session, $mpi->pareq);
            }
        } catch(Exception $e) {
            return new Response('Authentication failed', self::ERROR_RESPONSE);
        }
    }

    /**
     * @param string $method
     * @param null $uri
     * @param null $headers
     * @param null $body
     * @param $mpiData
     * @return \Guzzle\Http\Message\RequestInterface
     */
    public function createRemoteRequest($method = 'GET', $uri = null, $headers = null, $body = null, $mpiData)
    {
        $request = parent::createRequest($method, $uri, $headers, $body);

        if (!$body) {
            $request->createBody($this->getStoreId(), $this->getKey(), $this->getTransaction(), $this->getCard(), $this->getBillingInformation(), $this->getBrowser(), $mpiData);
        }

        return $request;
    }

    /**
     * @param string $method
     * @param string $uri
     * @param array $headers
     * @param string|resource|array|EntityBodyInterface $body
     * @return \Guzzle\Http\Message\RequestInterface
     */
    public function createMpiRequest($method = 'GET', $uri = null, $headers = null, $body = null)
    {
        $request = parent::createRequest($method, $uri, $headers, $body);

        if (!$body) {
            $request->createMpiBody($this->getStoreId(), $this->getKey(), $this->getTransaction(), $this->getCard(), $this->getBillingInformation(), $this->getBrowser());
        }

        return $request;
    }

    /**
     * Authorize mpi request
     *
     * @return array|\Guzzle\Http\Message\Response|null
     * @throws Exception\AuthFailed
     */
    protected function authorizeMpiRequest()
    {
        $response = $this->send($this->createMpiRequest('POST', self::INNOVATE_MPI_URL, null));

        if (!$response || !isset($response) || !empty($response->xml()->error)) {
            throw new AuthFailed();
        }

        return $response;
    }

    /**
     *
     *
     * @param \SimpleXMLElement $mpiData
     * @return array|\Guzzle\Http\Message\Response|null
     * @throws Exception\AuthFailed
     */
    public function authorizeRemoteRequest($mpiData)
    {
        $response   = $this->send($this->createRemoteRequest('POST', self::INNOVATE_URL, null, null, $mpiData));

        if (!$response || !isset($response) || $response->xml()->auth->status == self::RESULT_ERROR_STATUS) {
            return new Response('Authentication Failed', self::ERROR_RESPONSE);
        }

        return $response;
    }

    public function getStoreId()
    {
        return $this->storeId;
    }

    public function setStoreId($storeId)
    {
        $this->storeId = $storeId;
    }

    public function getKey()
    {
        return $this->key;
    }

    public function setKey($key)
    {
        $this->key = $key;
    }

    public function getTransaction()
    {
        return $this->transaction;
    }

    public function setTransaction(Transaction $transaction)
    {
        $this->transaction = $transaction;
    }

    public function setCard(Card $card)
    {
        $this->card = $card;
    }

    public function getCard()
    {
        return $this->card;
    }

    public function setBrowser($browser)
    {
        $this->browser = $browser;
    }

    public function getBrowser()
    {
        return $this->browser;
    }

    public function setBillingInformation($billingInformation)
    {
        $this->billingInformation = $billingInformation;
    }

    public function getBillingInformation()
    {
        return $this->billingInformation;
    }
}