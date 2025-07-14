<?php

namespace App\Services\Gateway\tzsmmpay;

use Exception;
use App\Models\Order;
use App\Models\Deposit;
use Illuminate\Http\Request;
use App\Services\OrderService;
use App\Services\DepositService;
use App\Services\Gateway\GatewayInterface;
use App\Services\Gateway\tzsmmpay\TzsmmPay;

class Payment implements GatewayInterface
{
    /**
     * Prepare deposit data for TZSMM Pay API.
     *
     * @param Deposit $deposit Deposit model instance
     * @param string $gateway Gateway name (e.g., 'TZSMM Pay')
     * @return array|Exception Returns array with redirect_url or throws Exception on failure
     */
    public static function prepareDepositData(Deposit $deposit, $gateway): array | Exception
    {
        $apiKey = gs()->tzsmmpay_api_key;
        $tzsmmPay = new TzsmmPay($apiKey);

        $requestData = [
            'cus_name'     => $deposit->user->name ?? 'Test User',
            'cus_email'    => $deposit->user->email ?? 'test@test.com',
            'cus_number'   => $deposit->user->number ?? '00000000',
            'amount'       => $deposit->amount,
            'currency'     => 'BDT',
            'success_url'  => route('user.addfunds'),
            'cancel_url'   => depositCancelUrl(),
            'callback_url' => depositRedirectUrl($deposit, $gateway),
            'extra'        => [
                'track_id' => $deposit->track_id,
                'addfund_id' => $deposit->id,
                'user_id' => $deposit->user->id ?? null,
            ],
        ];

        try {
            $paymentUrl = $tzsmmPay->initPayment($requestData);
            $response = [
                'redirect_url' => $paymentUrl,
            ];
        } catch (Exception $e) {
            throw new Exception("Initialization Error: " . $e->getMessage());
        }

        return $response;
    }

    /**
     * Handle IPN (webhook) for TZSMM Pay deposit verification.
     *
     * @param Request $request HTTP request containing webhook data
     * @param Deposit $deposit Deposit model instance
     * @param string $gateway Gateway name (e.g., 'TZSMM Pay')
     * @return array|Exception Returns array with status, message, and redirect URL or throws Exception on failure
     */
    public static function depositIpn(Request $request, Deposit $deposit, $gateway): array | Exception
    {
        $apiKey = gs()->tzsmmpay_api_key;
        $tzsmmPay = new TzsmmPay($apiKey);

        try {
            $response = $tzsmmPay->executePayment($request->all());
        } catch (Exception $e) {
            throw new Exception("Verification Error: " . $e->getMessage());
        }

        try {
            if ($response['status'] === 'Completed') {
                $depositService = new DepositService();
                $depositService->completeDeposit(
                    $deposit,
                    $response['payment_method'] ?? 'TZSMM Pay',
                    $response['trx_id']
                );
            } else {
                throw new Exception("Payment not completed. Status: " . ($response['status'] ?? 'Unknown'));
            }
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }

        $data = [
            'status'   => 'success',
            'message'  => __('Add Fund Successful.'),
            'redirect' => depositIpnRedirectUrl()
        ];
        return $data;
    }

    /**
     * Prepare order data for TZSMM Pay API.
     *
     * @param Order $order Order model instance
     * @param string $gateway Gateway name (e.g., 'TZSMM Pay')
     * @return array|Exception Returns array with redirect_url or throws Exception on failure
     */
    public static function prepareOrderData(Order $order, $gateway): array | Exception
    {
        $apiKey = gs()->tzsmmpay_api_key;
        $tzsmmPay = new TzsmmPay($apiKey);

        $requestData = [
            'cus_name'     => $order->user->name ?? 'Test User',
            'cus_email'    => $order->user->email ?? 'test@test.com',
            'cus_number'   => $order->user->number ?? '00000000',
            'amount'       => $order->amount,
            'currency'     => 'BDT',
            'success_url'  => route('user.addfunds'),
            'cancel_url'   => orderCancelUrl($order),
            'callback_url' => orderRedirectUrl($order, $gateway),
            'extra'        => [
                'order_id' => $order->id,
                'track_id' => $order->track_id,
                'user_id' => $order->user->id ?? null,
            ],
        ];

        try {
            $paymentUrl = $tzsmmPay->initPayment($requestData);
            $response = [
                'redirect_url' => $paymentUrl,
            ];
        } catch (Exception $e) {
            throw new Exception("Initialization Error: " . $e->getMessage());
        }

        return $response;
    }

    /**
     * Handle IPN (webhook) for TZSMM Pay order verification.
     *
     * @param Request $request HTTP request containing webhook data
     * @param Order $order Order model instance
     * @param string $gateway Gateway name (e.g., 'TZSMM Pay')
     * @return array|Exception Returns array with status, message, and redirect URL or throws Exception on failure
     */
    public static function orderIpn(Request $request, Order $order, $gateway): array | Exception
    {
        $apiKey = gs()->tzsmmpay_api_key;
        $tzsmmPay = new TzsmmPay($apiKey);

        try {
            $response = $tzsmmPay->executePayment($request->all());
        } catch (Exception $e) {
            throw new Exception("Verification Error: " . $e->getMessage());
        }

        try {
            if ($response['status'] === 'Completed') {
                $orderService = new OrderService();
                $orderService->completeOrder(
                    $order,
                    $response['payment_method'] ?? 'TZSMM Pay',
                    $response['trx_id']
                );
            } else {
                throw new Exception("Payment not completed. Status: " . ($response['status'] ?? 'Unknown'));
            }
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }

        $data = [
            'status'   => 'success',
            'message'  => __('Order Successful.'),
            'redirect' => orderIpnRedirectUrl($order)
        ];
        return $data;
    }
}
