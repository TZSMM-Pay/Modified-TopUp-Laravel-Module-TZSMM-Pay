<?php

namespace App\Services;

use App\Constants\Status;
use App\Models\Deposit;
use App\Models\Transaction;
use App\Services\Gateway\GatewayInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DepositService
{
    public function addFund(Request $request)
    {
        try {
            $gateway = 'tzsmmpay';
            $getwayObj = 'App\\Services\\Gateway\\' . $gateway . '\\Payment';
            $getwayObj = app($getwayObj);
            if (!($getwayObj instanceof GatewayInterface)) {
                dd("The Payment must implement GatewayInterface");
            }

            $create = Deposit::create([
                'user_id'  => user_id(),
                'amount'   => $request->amount,
                'track_id' => strRandom(),
            ]);

            $deposit = Deposit::where('id', $create->id)->orderBy('id', 'DESC')->with(['user'])->first();

            $data = $getwayObj::prepareDepositData($deposit, $gateway);
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
        return view($data->view, compact('data', 'page_title', 'deposit'));
    }

    public function payNow($depositId)
    {
        $deposit = Deposit::where('id', $depositId)->orderBy('id', 'DESC')->with(['user'])->first();

        try {
            $gateway = 'tzsmmpay';
            $getwayObj = 'App\\Services\\Gateway\\' . $gateway . '\\Payment';
            $getwayObj = app($getwayObj);
            if (!($getwayObj instanceof GatewayInterface)) {
                dd("The Payment must implement GatewayInterface");
            }
            $data = $getwayObj::prepareDepositData($deposit, $gateway);
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
        return view($data->view, compact('data', 'page_title', 'deposit'));
    }

    public function gatewayIpn(Request $request, string $trx, string $gateway)
    {
        try {
            if ($gateway !== 'tzsmmpay') {
                return redirect()->route('user.addfunds')->with('error', __('Payment gateway being used is not valid or recognized.'));
            }

            $deposit = Deposit::where('track_id', $trx)->orderBy('id', 'desc')->first();
            if (!$deposit) {
                throw new \Exception(__('Deposit ID is not found.'));
            }

            $getwayObj = 'App\\Services\\Gateway\\' . $gateway . '\\Payment';
            $getwayObj = new $getwayObj();
            if (!($getwayObj instanceof GatewayInterface)) {
                dd("The Payment must implement GatewayInterface");
            }
            $data = $getwayObj::depositIpn($request, $deposit, $gateway);
        } catch (\Exception $exception) {
            return redirect()->route('user.addfunds')->with('error', $exception->getMessage());
        }

        if (isset($data['redirect'])) {
            return redirect($data['redirect'])->with($data['status'], $data['message']);
        }
    }

    public function completeDeposit(Deposit $deposit, string $paymentMethod, string $transactionId)
    {
        $exists = Transaction::where('transaction_id', $transactionId)->exists();
        if ($exists) {
            throw new \Exception(__('Transaction ID already exists.'));
        }

        DB::transaction(function () use ($deposit, $paymentMethod, $transactionId) {
            if ($deposit->status == Status::UNPAID) {
                // Update Deposit
                $deposit['status'] = Status::PAID;
                $deposit->update();

                // Update User
                $user = $deposit->user;
                $user->balance += $deposit->amount;
                $user->save();

                // Add Transaction
                $transaction = new Transaction();
                $transaction->user_id = $deposit->user_id;
                $transaction->deposit_id = $deposit->id;
                $transaction->trx_type = Status::DEBIT;
                $transaction->amount = $deposit->amount;
                $transaction->payment_method = $paymentMethod;
                $transaction->remarks = 'Deposit is being made using the ' . $paymentMethod;
                $transaction->transaction_id = $transactionId;
                $transaction->save();
            }
        }, 5);
    }
}
