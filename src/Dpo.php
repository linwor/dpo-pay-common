<?php

namespace Dpo\Common;

class Dpo
{
    public static string $testApiUrl = 'https://secure.3gdirectpay.com/API/v6/';
    public static string $testPayUrl = 'https://secure.3gdirectpay.com/payv2.php';
    public static string $liveApiUrl = 'https://secure.3gdirectpay.com/API/v6/';
    public static string $livePayUrl = 'https://secure.3gdirectpay.com/payv2.php';
    protected array $createResponses = [
        '000' => 'Transaction created',
        '801' => 'Request missing company token',
        '802' => 'Company token does not exist',
        '803' => 'No request or error in Request type name',
        '804' => 'Error in XML',
        '902' => 'Request missing transaction level mandatory fields - name of field',
        '904' => 'Currency not supported',
        '905' => 'The transaction amount has exceeded your allowed transaction limit,
         please contact: support@directpay.online',
        '906' => 'You exceeded your monthly transactions limit, please contact: support@directpay.online',
        '922' => 'Provider does not exist',
        '923' => 'Allocated money exceeds payment amount',
        '930' => 'Block payment code incorrect',
        '940' => 'CompanyREF already exists and paid',
        '950' => 'Request missing mandatory fields - name of field',
        '960' => 'Tag has been sent multiple times',
    ];
    protected array $createResponseCodes;
    protected array $verifyResponses = [
        '000' => 'Transaction Paid',
        '001' => 'Authorized',
        '002' => 'Transaction overpaid/underpaid',
        '801' => 'Request missing company token',
        '802' => 'Company token does not exist',
        '803' => 'No request or error in Request type name',
        '804' => 'Error in XML',
        '900' => 'Transaction not paid yet',
        '901' => 'Transaction declined',
        '902' => 'Data mismatch in one of the fields - field (explanation)',
        '903' => 'The transaction passed the Payment Time Limit',
        '904' => 'Transaction cancelled',
        '950' => 'Request missing transaction level mandatory fields – field (explanation)',
    ];
    protected array $verifyResponseCodes;
    protected string $apiUrl;
    protected string $payUrl;

    public function __construct(bool $test_mode)
    {
        $this->createResponseCodes = array_flip($this->createResponses);
        $this->verifyResponseCodes = array_flip($this->verifyResponses);

        if ($test_mode) {
            $this->apiUrl = self::$testApiUrl;
            $this->payUrl = self::$testPayUrl;
        } else {
            $this->apiUrl = self::$liveApiUrl;
            $this->payUrl = self::$livePayUrl;
        }
    }

    /**
     * Create DPO token for payment processing
     *
     * @param array $data
     *
     * @return array|string
     */
    public function createToken(array $data): array|string
    {
        $service = '';

        $serviceDate = date('Y/m/d H:i');
        $serviceDesc = 'test';

        // Create each product service xml
        $service .= <<<POSTXML
            <Service>
                <ServiceType>{$data['serviceType']}</ServiceType>
                <ServiceDescription>$serviceDesc</ServiceDescription>
                <ServiceDate>$serviceDate</ServiceDate>
            </Service>
POSTXML;

        $customerPhone             = preg_replace('/\D/', '', $data['customerPhone'] ?? '');
        $data['customerDialCode']  = $data['customerDialCode'] ?? '';
        $data['customerZip']       = $data["customerZip"] ?? '';
        $data['customerCountry']   = $data['customerCountry'] ?? '';
        $data['customerAddress']   = $data['customerAddress'] ?? '';
        $data['customerCity']      = $data['customerCity'] ?? '';
        $data['customerEmail']     = $data['customerEmail'] ?? '';
        $data['customerFirstName'] = $data['customerFirstName'] ?? '';
        $data['customerLastName']  = $data['customerLastName'] ?? '';

        $postXml = <<<POSTXML
        <?xml version="1.0" encoding="utf-8"?><API3G>
        <CompanyToken>{$data['companyToken']}</CompanyToken>
        <Request>createToken</Request>
        <Transaction>
        <PaymentAmount>{$data['paymentAmount']}</PaymentAmount>
        <PaymentCurrency>{$data['paymentCurrency']}</PaymentCurrency>
        <CompanyRef>{$data['companyRef']}</CompanyRef>
        <customerDialCode>{$data['customerDialCode']}</customerDialCode>
        <customerZip>{$data['customerZip']}</customerZip>
        <customerCountry>{$data['customerCountry']}</customerCountry>
        <customerFirstName>{$data['customerFirstName']}</customerFirstName>
        <customerLastName>{$data['customerLastName']}</customerLastName>
        <customerAddress>{$data['customerAddress']}</customerAddress>
        <customerCity>{$data['customerCity']}</customerCity>
        <customerPhone>{$customerPhone}</customerPhone>
        <RedirectURL>{$data['redirectURL']}</RedirectURL>
        <BackURL>{$data['backURL']}</BackURL>
        <customerEmail>{$data['customerEmail']}</customerEmail>
POSTXML;

        if (!empty($data['PTL'])) {
            $postXml .= "<PTL>{$data['PTL']}</PTL>";
            $postXml .= "<PTLtype>{$data['PTLtype']}</PTLtype>";
        }

        $postXml .= <<<POSTXML
        </Transaction>
        <Services>$service</Services>
        </API3G>
POSTXML;

        $error = '';

        try {
            $curl = curl_init();
            curl_setopt_array(
                $curl,
                array(
                    CURLOPT_URL            => $this->apiUrl,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING       => "",
                    CURLOPT_MAXREDIRS      => 10,
                    CURLOPT_TIMEOUT        => 30,
                    CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST  => "POST",
                    CURLOPT_POSTFIELDS     => $postXml,
                    CURLOPT_HTTPHEADER     => array(
                        "cache-control: no-cache",
                    ),
                )
            );
            $response = curl_exec($curl);
            curl_close($curl);
        } catch (\Exception $exception) {
            $error .= "Curl error in createToken: " . $exception->getMessage();
        }

        $xml = new \SimpleXMLElement($response);

        // Check if token creation response has been received
        if (!in_array($xml->xpath('Result')[0]->__toString(), array_keys($this->createResponses))) {
            return "Error in getting Transaction Token: Invalid response: " . $response;
        } elseif ($xml->xpath('Result')[0]->__toString() === '000') {
            $transToken        = $xml->xpath('TransToken')[0]->__toString();
            $result            = $xml->xpath('Result')[0]->__toString();
            $resultExplanation = $xml->xpath('ResultExplanation')[0]->__toString();
            $transRef          = $xml->xpath('TransRef')[0]->__toString();

            return [
                'success'           => true,
                'result'            => $result,
                'transToken'        => $transToken,
                'resultExplanation' => $resultExplanation,
                'transRef'          => $transRef,
            ];
        } else {
            return [
                'success'   => false,
                'errorcode' => $xml->xpath('Result')[0]->__toString(),
                'error'     => $xml->xpath('ResultExplanation')[0]->__toString() . " $error",
            ];
        }
    }

    /**
     * Verify the DPO token created in the first step
     *
     * @param array $data
     *
     * @return string
     */
    public function verifyToken(array $data): string
    {
        $companyToken = $data['companyToken'];
        $transToken   = $data['transToken'];

        $verified = false;
        $cnt      = 0;
        $response = '';

        while (!$verified && $cnt < 10) {
            try {
                $curl = curl_init();
                curl_setopt_array(
                    $curl,
                    array(
                        CURLOPT_URL            => 'https://secure.3gdirectpay.com/API/v7/',
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_ENCODING       => "",
                        CURLOPT_MAXREDIRS      => 10,
                        CURLOPT_TIMEOUT        => 30,
                        CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
                        CURLOPT_CUSTOMREQUEST  => "POST",
                        CURLOPT_POSTFIELDS     => <<<FIELDS
<?xml version=\"1.0\" encoding=\"utf-8\"?>
<API3G>
<CompanyToken>$companyToken</CompanyToken>
<Request>verifyToken</Request>
<TransactionToken>$transToken</TransactionToken>
</API3G>
FIELDS,
                        CURLOPT_HTTPHEADER     => ["cache-control: no-cache",],
                    )
                );

                $response = curl_exec($curl);
                $err      = curl_error($curl);

                curl_close($curl);

                if (strlen($err) > 0) {
                    $cnt++;
                } else {
                    $verified = true;
                }
            } catch (Exception) {
                $cnt++;
            }
        }

        return $response;
    }

    /**
     * @param string $code
     *
     * @return string
     */
    public function getCreateResponse(string $code): string
    {
        return $this->createResponses[$code];
    }

    /**
     * @param string $code
     *
     * @return string
     */
    public function getVerifyResponse(string $code): string
    {
        return $this->verifyResponses[$code];
    }

    /**
     * @param string $description
     *
     * @return string
     */
    public function getCreateResponseCode(string $description): string
    {
        return $this->createResponseCodes[$description];
    }

    /**
     * @param string $description
     *
     * @return string
     */
    public function getVerifyResponseCode(string $description): string
    {
        return $this->verifyResponseCodes[$description];
    }

    /**
     * @return string
     */
    public function getPayUrl(): string
    {
        return $this->payUrl;
    }

    /**
     * @return string
     */
    public function getApiUrl(): string
    {
        return $this->apiUrl;
    }
}
