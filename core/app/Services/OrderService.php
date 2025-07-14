<?php

namespace App\Services;

use App\Constants\OrderStatus;
use App\Constants\Role;
use App\Constants\Status;
use App\Filters\OrderFilter;
use App\Mail\OrderPlaced;
use App\Models\AutoVoucher;
use App\Models\Deposit;
use App\Models\Order;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Variation;
use App\Models\Voucher;
use App\Services\Gateway\GatewayInterface;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class OrderService
{
    public function getMine(array $queryParams = [], bool $isVoucher = false)
    {
        $queryBuilder = Order::with(['variation', 'product', 'voucher'])
            ->where('user_id', user_id())
            ->latest();

        if ($isVoucher) {
            $queryBuilder->whereHas('product', function (Builder $query) {
                $query->where('type', Status::VOUCHER);
            });
        } else {
            $queryBuilder->whereDoesntHave('product', function (Builder $query) {
                $query->where('type', Status::VOUCHER);
            });
        }

        $orders = app(OrderFilter::class)->getResults([
            'builder' => $queryBuilder,
            'params'  => $queryParams,
        ]);

        return $orders;
    }

    public function addOrder(Request $request)
    {
        $variation = Variation::where('stock', '>', 0)
            ->with(['product', 'vouchers' => function ($query) {
                $query->where('status', Status::AVAILABLE);
            }])
            ->findOrFail($request->variation_id);

        if ($variation->product->isVoucher() && $variation->vouchers->count() < $request->input('quantity', 1)) {
            return back()->with('error', __('Sorry, this voucher is out of stock.'));
        }

        $amount_cal = $variation->price * $request->input('quantity', 1);
        $variation_buy_rate = $variation->buy_rate;
        $profit_cal = 0.00;

        if($amount_cal>$variation_buy_rate)
        {
            $profit_cal = $amount_cal-$variation_buy_rate;
            $profit_cal = number_format($profit_cal, 2, '.', '');
        }

        $orderData = [
            'user_id'      => user_id(),
            'product_id'   => $variation->product->id,
            'variation_id' => $variation->id,
            'quantity'     => $request->input('quantity', 1),
            'amount'       => $amount_cal,
            'profit'       => $profit_cal,
            'track_id'     => strRandom(),
        ];

        if (in_array($variation->product->type, [Status::TOPUP, Status::INGAME])) {
            $orderData['account_info'] = $request->input('account_info');
        }

        try {
            $order = DB::transaction(function () use ($orderData) {
                return Order::create($orderData);
            });
        } catch (Exception $e) {
            return back()->with('error', __('Something went wrong.'));
        }

        $order = Order::where('id', $order->id)->orderBy('id', 'DESC')->with(['user', 'variation', 'product'])->first();

        if (gs()->wallet && $request->payment_method === Status::WALLET) {
            try {
                $this->completeOrderWithWallet($order, $request->payment_method);
                $redirect = ($order->product->isVoucher()) ? route('user.codes') : route('user.orders');
                return redirect($redirect)->with('success', __('Order Successfull.'));
            } catch (\Exception $exception) {
                return back()->with('error', $exception->getMessage());
            }
        }

        try {
            $gateway = 'tzsmmpay';
            $getwayObj = 'App\\Services\\Gateway\\' . $gateway . '\\Payment';
            $getwayObj = app($getwayObj);
            if (!($getwayObj instanceof GatewayInterface)) {
                dd("The Payment must implement GatewayInterface");
            }
            $data = $getwayObj::prepareOrderData($order, $gateway);
            $data = (object) $data;
        } catch (\Exception $exception) {
            return back()->with('error', $exception->getMessage());
        }

        if (isset($data->error)) {
            return back()->with('error', $data->message);
        }

        if (isset($data->redirect_url)) {
            return redirect($data->redirect_url);
        }

        $page_title = 'Payment Confirm';
        return view($data->view, compact('data', 'page_title', 'order'));
    }

    public function payNow($orderId)
    {
        $order = Order::where('id', $orderId)->orderBy('id', 'DESC')->with(['user', 'variation', 'product'])->first();

        $variation = Variation::where('stock', '>', 0)->with(['product', 'vouchers' => function ($query) {
            $query->where('status', Status::AVAILABLE);
        }])->find($order->variation_id);

        if (!$variation) {
            return back()->with('error', __('Sorry, this product is out of stock.'));
        }

        if ($variation->product->isVoucher() && $variation->vouchers->count() < 1) {
            return back()->with('error', __('Sorry, this voucher is out of stock.'));
        }

        if (gs()->wallet === Status::ACTIVE && auth()->user()->balance > $order->amount) {
            try {
                $this->completeOrderWithWallet($order, Status::WALLET);
                $redirect = ($order->product->isVoucher()) ? route('user.codes') : route('user.orders');
                return redirect($redirect)->with('success', __('Order Successful.'));
            } catch (\Exception $exception) {
                return back()->with('error', $exception->getMessage());
            }
        }

        try {
            $gateway = 'tzsmmpay';
            $getwayObj = 'App\\Services\\Gateway\\' . $gateway . '\\Payment';
            $getwayObj = app($getwayObj);
            if (!($getwayObj instanceof GatewayInterface)) {
                dd("The Payment must implement GatewayInterface");
            }
            $data = $getwayObj::prepareOrderData($order, $gateway);
            $data = (object) $data;
        } catch (\Exception $exception) {
            return back()->with('error', $exception->getMessage());
        }

        if (isset($data->error)) {
            return back()->with('error', $data->message);
        }

        if (isset($data->redirect_url)) {
            return redirect($data->redirect_url);
        }

        $page_title = 'Payment Confirm';
        return view($data->view, compact('data', 'page_title', 'order'));
    }

    public function gatewayIpn(Request $request, string $trx, string $gateway)
    {
        try {
            if ($gateway !== 'tzsmmpay') {
                return redirect()->route('user.addfunds')->with('error', __('Payment gateway being used is not valid or recognized.'));
            }

            $order = Order::where('track_id', $trx)->orderBy('id', 'DESC')->with(['user', 'variation', 'product'])->first();
            if (!$order) {
                throw new \Exception(__('Order ID is not found.'));
            }

            $getwayObj = 'App\\Services\\Gateway\\' . $gateway . '\\Payment';
            $getwayObj = app($getwayObj);
            if (!($getwayObj instanceof GatewayInterface)) {
                dd("The Payment must implement GatewayInterface");
            }
            $data = $getwayObj::orderIpn($request, $order, $gateway);
        } catch (\Exception $exception) {
            return redirect()->route('user.account')->with('error', $exception->getMessage());
        }

        if (isset($data['redirect'])) {
            return redirect($data['redirect'])->with($data['status'], $data['message']);
        }
    }

    public function completeOrder(Order $order, string $paymentMethod, string $transactionId, ?Voucher $vouchers = null)
    {
        $exists = Transaction::where('transaction_id', $transactionId)->exists();
        if ($exists) {
            throw new \Exception(__('Transaction ID already exists.'));
        }

        $variation = Variation::where('stock', '>', 0)->with(['product', 'vouchers' => function ($query) {
            $query->where('status', Status::AVAILABLE);
        }])->find($order->variation_id);

        if (!$variation) {
            $this->completeDeposit($order, $paymentMethod, $transactionId);
            throw new \Exception(__('Sorry, this product is out of stock. Your payment amount has been debited to your wallet.'));
        }

        if ($order->product->isVoucher()) {
            $vouchers = Voucher::where('status', Status::AVAILABLE)
                ->where('variation_id', $order->variation_id)
                ->limit($order->quantity)
                ->orderBy('id', 'DESC')
                ->get();

            if ($vouchers->count() < $order->quantity) {
                throw new \Exception(__('Insufficient vouchers available.'));
            }
        }

        DB::transaction(function () use ($order, $paymentMethod, $transactionId, $vouchers) {
            if ($order->isPending()) {
                // Update Order
                $order['status'] = ($order->product->isVoucher()) ? Status::COMPLETED : Status::PROCESSING;
                $order->update();

                if ($order->product->isVoucher()) {
                    // Update Variation
                    $variation = $order->variation;
                    $variation->stock -= $vouchers->count();
                    $variation->save();
                    // Update Voucher
                    $voucherCodes = [];
                    foreach ($vouchers as $index => $voucher) {
                        $voucherCodes[] = $voucher->code;
                        $voucher->status = Status::SOLD;
                        $voucher->order_id = $order->id;   
                        $voucher->save();

                        if ($index < $order->quantity - 1) {
                            $voucherCodes[] = ',';
                        }
                    }

                    // Update Order
                    $order['voucher_code'] = implode('', $voucherCodes);
                    $order->update();
                } else {
                    // Update Variation
                    $variation = $order->variation;
                    $variation->stock -= 1;
                    $variation->save();
                }

                // Add Transaction
                $transaction = new Transaction();
                $transaction->user_id = $order->user_id;
                $transaction->order_id = $order->id;
                $transaction->trx_type = Status::CREDIT;
                $transaction->amount = $order->amount;
                $transaction->payment_method = $paymentMethod;
                $transaction->remarks = "Product is being purchased using the {$paymentMethod}, Order ID: {$order->id}";
                $transaction->transaction_id = $transactionId;
                $transaction->save();

                // Add Transaction Id to voucher or autovoucher
                $this->addTransactionId($order->id,$transactionId);

                // Handle Reseller
                $this->handleReseller($order);

                try {
                    if (gs()->smtp_from_address && gs()->smtp_host && gs()->smtp_password && gs()->smtp_port && gs()->smtp_username) {
                        foreach (User::where('role', Role::ADMIN)->cursor() as $admin) {
                            Mail::to($admin->email)->queue(new OrderPlaced($order));
                        }
                    }
                } catch (Exception $e) {
                    //
                }

                try {
                    $autoVoucher = AutoVoucher::where('status', Status::AVAILABLE)
                        ->where('variation_id', $order->variation_id)
                        ->first();

                    if ($order->product->isTopup() && $order->variation->isAutomatic() && $autoVoucher && gs()->enable_auto_topup && gs()->free_fire_server_url && gs()->free_fire_server_api_key) {
                        $order->status = OrderStatus::AUTOPROCESSING;
                        $order->voucher_code = $autoVoucher->code;
                        $order->save();

                        $autoVoucher->status = Status::SOLD;
                        $autoVoucher->save();
                    }
                } catch (Exception $e) {
                    //
                }
            }
        }, 5);
    }

    public function completeOrderWithWallet(Order $order, string $paymentMethod, ?Voucher $vouchers = null)
    {
        if ($order->amount > auth()->user()->balance) {
            throw new \Exception(__('Insufficient Balance.'));
        }

        $variation = Variation::where('stock', '>', 0)->with(['product', 'vouchers' => function ($query) {
            $query->where('status', Status::AVAILABLE);
        }])->find($order->variation_id);

        if (!$variation) {
            throw new \Exception(__('Sorry, this product is out of stock.'));
        }

        if ($order->product->isVoucher()) {
            $vouchers = Voucher::where('status', Status::AVAILABLE)
                ->where('variation_id', $order->variation_id)
                ->limit($order->quantity)
                ->orderBy('id', 'DESC')
                ->get();

            if ($vouchers->count() < $order->quantity) {
                throw new \Exception(__('Insufficient vouchers available.'));
            }
        }

        DB::transaction(function () use ($order, $vouchers, $paymentMethod) {
            if ($order->isPending()) {
                // Update Order
                $order['status'] = ($order->product->isVoucher()) ? Status::COMPLETED : Status::PROCESSING;
                $order->update();

                // Update User
                $user = $order->user;
                $user->balance -= $order->amount;
                $user->save();

                if ($order->product->isVoucher()) {

                    // Update Variation
                    $variation = $order->variation;
                    $variation->stock -= $vouchers->count();
                    $variation->save();

                    // Update Voucher
                    $voucherCodes = [];
                    foreach ($vouchers as $index => $voucher) {
                        //$voucherCodes[] = $voucher->code;
                         if (is_array($voucher->code)) {
                                $voucherCodes[] = implode(',', $voucher->code);
                            } else {
                                $voucherCodes[] = $voucher->code;
                            }
    
                        $voucher->status = Status::SOLD;
                        $voucher->order_id = $order->id;
                        $voucher->save();

                        if ($index < $order->quantity - 1) {
                            $voucherCodes[] = ',';
                        }
                    }

                    // Update Order
                    $order['voucher_code'] = implode('', $voucherCodes);
                    $order->update();
                } else {
                    // Update Variation
                    $variation = $order->variation;
                    $variation->stock -= 1;
                    $variation->save();
                }

                // Add Transaction
                $transaction = new Transaction();
                $transaction->user_id = $order->user_id;
                $transaction->order_id = $order->id;
                $transaction->trx_type = Status::CREDIT;
                $transaction->amount = $order->amount;
                $transaction->payment_method = $paymentMethod;
                $transaction->remarks = "Product is being purchased using the {$paymentMethod}, Order ID: {$order->id}";
                $transactionId  = strRandom();
                $transaction->transaction_id = $transactionId;
                $transaction->save();


                // Add Transaction Id to voucher or autovoucher
                $this->addTransactionId($order->id,$transactionId);

                // Handle Reseller
                $this->handleReseller($order);

                try {
                    if (gs()->smtp_from_address && gs()->smtp_host && gs()->smtp_password && gs()->smtp_port && gs()->smtp_username) {
                        foreach (User::where('role', Role::ADMIN)->cursor() as $admin) {
                            Mail::to($admin->email)->queue(new OrderPlaced($order));
                        }
                    }
                } catch (Exception $e) {
                    //
                }

                try {
                    $autoVoucher = AutoVoucher::where('status', Status::AVAILABLE)
                        ->where('variation_id', $order->variation_id)
                        ->first();

                    if ($order->product->isTopup() && $order->variation->isAutomatic() && $autoVoucher && gs()->enable_auto_topup && gs()->free_fire_server_url && gs()->free_fire_server_api_key) {
                        $order->status = OrderStatus::AUTOPROCESSING;
                        $order->voucher_code = $autoVoucher->code;
                        $order->save();

                        $autoVoucher->order_id = $order->id;


                        $autoVoucher->status = Status::SOLD;
                        $autoVoucher->save();
                    }
                } catch (Exception $e) {
                    //
                }
            }
        }, 5);
    }

    private function completeDeposit(Order $order, string $paymentMethod, string $transactionId)
    {
        DB::transaction(function () use ($order, $paymentMethod, $transactionId) {
            // Update User
            $user = $order->user;
            $user->balance += $order->amount;
            $user->save();

            // Add Deposit
            $deposit = new Deposit();
            $deposit->user_id = user_id();
            $deposit->amount = $order->amount;
            $deposit->track_id = strRandom();
            $deposit->status = Status::PAID;
            $deposit->save();

            // Add Transaction
            $transaction = new Transaction();
            $transaction->user_id = $order->user_id;
            $transaction->deposit_id = $deposit->id;
            $transaction->trx_type = Status::DEBIT;
            $transaction->amount = $order->amount;
            $transaction->payment_method = $paymentMethod;
            $transaction->remarks = 'Deposit is being made using the ' . $paymentMethod;
            $transaction->transaction_id = $transactionId;
            $transaction->save();

            // Add Transaction Id to voucher or autovoucher 
            $this->addTransactionId($order->id,$transactionId);

        }, 5);
    }

    public static function cancelOrder(Order $order)
    {
        DB::transaction(function () use ($order) {
            // Update User
            $user = $order->user;
            $refundAmount = $order->amount;
            if ($user->isReseller()) {
                $percentageAmount = getPercentageAmount($order->amount, $order->product->percentage);
                $refundAmount -= $percentageAmount;
            }
            $user->balance += $refundAmount;
            $user->save();

            // Add Transaction
            $transaction = new Transaction();
            $transaction->user_id = $order->user_id;
            $transaction->order_id = $order->id;
            $transaction->trx_type = Status::DEBIT;
            $transaction->amount = $order->amount;
            $transaction->payment_method = Status::WALLET;
            $transaction->remarks = 'A deposit is being made for the cancellation of order ID: ' . $order->id;
            $transaction->transaction_id = strRandom();
            $transaction->save();
        }, 5);
    }

    private function handleReseller(Order $order)
    {
        $user = $order->user;
        if ($user->isReseller()) {
            $percentageAmount = getPercentageAmount($order->amount, $order->product->percentage);
            $user->balance += $percentageAmount;
            $user->save();

            // Add Transaction
            $transaction = new Transaction();
            $transaction->user_id = $order->user_id;
            $transaction->order_id = $order->id;
            $transaction->trx_type = Status::DEBIT;
            $transaction->amount = $percentageAmount;
            $transaction->payment_method = Status::WALLET;
            $transaction->remarks = 'Reseller bonus for order ID: ' . $order->id;
            $transaction->transaction_id = strRandom();
            $transaction->save();
        }
    }
            private function addTransactionId($order_id,$transactionId)
            {
                    $vouchers = Voucher::where('order_id', $order_id)->get();
                    $autovouchers = AutoVoucher::where('order_id', $order_id)->get();


                    if ($vouchers->count() > 0) {

                         foreach ($vouchers as $voucher) {
                        $voucher->transaction_id = $transactionId;
                        $voucher->save();
                             }
                    }
                    elseif ($autovouchers->count() > 0) {

                         foreach ($autovouchers as $autovoucher) {
                        $autovoucher->transaction_id = $transactionId;
                        $autovoucher->save();
                             }
                    }
                }
}
