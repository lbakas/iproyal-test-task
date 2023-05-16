<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use App\Models\Transaction;
use Illuminate\Http\Request;

class StatisticsController extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    public function getIndex(Request $request) {

        if (!$request->input('date_from') || !$request->input('date_to') || !$request->input('user_id')) {
            return [
                'Error' => 'Missing mandatory inputs: date_from or date_to or user_id'
            ];
        }

        $transactions = Transaction::query()
            ->whereDate('created_at', '>=', $request->input('date_from'))
            ->whereDate('created_at', '<=', $request->input('date_to'))
            ->where('userId', $request->input('user_id'));

        $depositsAmount = 0;
        $ordersAmount = 0;
        $refundsAmount = 0;
        $depositsCount = 0;
        $ordersCount = 0;
        $refundsCount = 0;
        $totalAmount = 0;

        $transactionsCount = $transactions->count();

        foreach($transactions->get() as $item) {
            if ($item['type'] == 'O') {
                $ordersAmount += $item['amount'];
                $ordersCount++;
            }
            if ($item['type'] == 'D') {
                $depositsAmount += $item['amount'];
                $depositsCount++;
            }
            if ($item['type'] == 'R') {
                $refundsAmount += $item['amount'];
                $refundsCount++;
            }
            $totalAmount += $item['amount'];
        }

        $averageAmount = round($totalAmount/$transactionsCount, 2);

        $result = [
            'Total number of transactions' => $transactionsCount,
            'Total number of deposits transactions' => $depositsCount,
            'Total number of orders transactions' => $ordersCount,
            'Total number of refunds transactions' => $refundsCount,
            'Total amount of deposits in USD' => round($depositsAmount, 2),
            'Total amount of orders in USD' => round($ordersAmount, 2),
            'Total amount of refunds in USD' => round($refundsAmount, 2),
            'Average transaction amount in USD' => $averageAmount,
        ];

        if ($request->input('currency_abb')) {
            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => "https://api.apilayer.com/exchangerates_data/convert?to=".$request->input('currency_abb')."&from=USD&amount=".$averageAmount,
                CURLOPT_HTTPHEADER => array(
                    "Content-Type: text/plain",
                    "apikey: AZlbCTbIZt4nL1YOr9gL4dL6tsoOIC9j"
                ),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "GET",
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_SSL_VERIFYPEER => 0
            ));
    
            $convertedAverageAmount = round(json_decode(curl_exec($curl))->result, 2);
    
            curl_close($curl);

            $result['Average transaction amount in '.$request->input('currency_abb')] = $convertedAverageAmount;
        }

        return $result;
    }
}
