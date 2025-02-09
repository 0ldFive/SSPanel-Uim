<?php

declare(strict_types=1);

namespace App\Services\Gateway;

use App\Models\Paylist;
use App\Models\Setting;
use App\Services\Auth;
use App\Services\Gateway\CoinPay\CoinPayApi;
use App\Services\Gateway\CoinPay\CoinPayConfig;
use App\Services\Gateway\CoinPay\CoinPayException;
use App\Services\Gateway\CoinPay\CoinPayUnifiedOrder;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Request;
use Slim\Http\Response;

final class CoinPay extends AbstractPayment
{
    private $coinPaySecret;
    private $coinPayGatewayUrl;
    private $coinPayAppId;

    public function __construct($coinPaySecret, $coinPayAppId)
    {
        $configs = Setting::getClass('coinpay');
        $this->coinPaySecret = $configs['coinpay_secret'];
        $this->coinPayAppId = $configs['coinpay_appid'];
        $this->coinPayGatewayUrl = 'https://openapi.coinpay.la/'; // 网关地址
    }

    public static function _name(): string
    {
        return 'coinpay';
    }

    public static function _enable(): bool
    {
        return self::getActiveGateway('coinpay');
    }

    public static function _readableName(): string
    {
        return 'CoinPay 支持BTC、ETH、USDT等数十种数字货币';
    }

    public function purchase(Request $request, Response $response, array $args): ResponseInterface
    {
        // set timezone
        date_default_timezone_set('Asia/Hong_Kong');
        /*请求参数*/
        $amount = $request->getParam('price');
        //var_dump($request->getParam("price"));die();
        $user = Auth::getUser();
        $pl = new Paylist();
        $pl->userid = $user->id;
        $pl->total = $amount;
        $pl->tradeno = self::generateGuid();
        $pl->save();
        //商户订单号，商户网站订单系统中唯一订单号，必填
        $out_trade_no = $pl->tradeno;
        //订单名称，必填
        $subject = $pl->id . 'UID:' . $user->id . ' 充值' . $amount . '元';
        //付款金额，必填
        $total_fee = (float) $amount;

        $report_data = new CoinPayUnifiedOrder();
        $report_data->setSubject($subject);
        $report_data->setOutTradeNo($out_trade_no);
        $report_data->setTotalAmount($total_fee);
        $report_data->setTimestamp(date('Y-m-d H:i:s', time()));
        $report_data->setReturnUrl($_ENV['baseUrl'] . '/user/code');
        $report_data->setNotifyUrl(self::getCallbackUrl());
//        $report_data->SetBody(json_encode($pl));
//        $report_data->SetTransCurrency("CNY");
//        $report_data->SetAttach("");
        $config = new CoinPayConfig();
        try {
            $url = CoinPayApi::unifiedOrder($config, $report_data);
            return json_encode(['code' => 0, 'url' => $this->coinPayGatewayUrl . 'api/gateway?' . $url]);
        } catch (CoinPayException $exception) {
            print_r($exception->getMessage());
            die;
        }
    }

    public function verify($data, $sign): bool
    {
        $payConfig = new CoinPayConfig();
        if ($sign === self::sign($data, $payConfig->getSecret())) {
            return true;
        }
        return false;
    }

    /**
     * 异步通知
     *
     * @param array $args
     */
    public function notify($request, $response, $args): ResponseInterface
    {
        $raw = file_get_contents('php://input');
        file_put_contents(BASE_PATH . '/coinpay_purchase.log', $raw . "\r\n", FILE_APPEND);
        $data = json_decode($raw, true);
        if (is_null($data)) {
            file_put_contents(BASE_PATH . '/coinpay_purchase.log', "返回数据异常\r\n", FILE_APPEND);
            echo 'fail';
            die;
        }
        // 签名验证
        $sign = $data['sign'];
        unset($data['sign']);
        $resultVerify = self::verify($data, $sign);
        $isPaid = $data !== null && $data['trade_status'] !== null && $data['trade_status'] === 'TRADE_SUCCESS';
        if ($resultVerify) {
            if ($isPaid) {
                $this->postPayment($data['out_trade_no'], 'CoinPay');
                echo 'success';
                file_put_contents(BASE_PATH . '/coinpay_purchase.log', "订单{$data['out_trade_no']}支付成功\r\n" . json_encode($data) . "\r\n", FILE_APPEND);
            } else {
                echo 'success';
                file_put_contents(BASE_PATH . '/coinpay_purchase.log', "订单{$data['out_trade_no']}未支付自动关闭成功\r\n" . json_encode($data) . "\r\n", FILE_APPEND);
            }
        } else {
            echo 'fail';
            file_put_contents(BASE_PATH . '/coinpay_purchase.log', "订单{$data['out_trade_no']}签名验证失败或者订单未支付成功\r\n" . json_encode($data) . "\r\n", FILE_APPEND);
        }
        die;
    }

    public static function getPurchaseHTML(): string
    {
        return '<div class="card-inner">
						<div class="form-group pull-left">
                            <p class="modal-title">CoinPay 支持BTC、ETH、USDT等数十种数字货币</p>
                            <div class="form-group form-group-label">
                                <label class="floating-label" for="amount-coinpay">充值金额</label>
                                <input id="amount-coinpay" class="form-control maxwidth-edit" name="amount-coinpay" />
                            </div>
                             <a class="btn btn-flat waves-attach" id="submitCoinPay" style="padding: 8px 24px;color: #fff;background: #1890ff;"><span class="icon">check</span>&nbsp;充&nbsp;值&nbsp;</a>
                        </div>
                    </div>
                        <script>
                        window.onload = function(){
        $("#submitCoinPay").click(function() {
            var price = parseFloat($("#amount-coinpay").val());
            if (isNaN(price)) {
                $("#result").modal();
                $("#msg").html("非法的金额!");
                return false;
            }
            $(\'#readytopay\').modal();
            $("#readytopay").on(\'shown.bs.modal\', function () {
                $.ajax({
                    \'url\': "/user/payment/purchase/coinpay",
                    \'data\': {
                        \'price\': price,
                    },
                    \'dataType\': \'json\',
                    \'type\': "POST",
                    success: (data) => {
                        if (data.code == 0) {
                            $("#result").modal();
                            $("#msg").html("正在跳转CoinPay支付网关...");
                            window.location.href = data.url;
                        } else {
                            $("#result").modal();
                            $$.getElementById(\'msg\').innerHTML = data.msg;
                            console.log(data);
                        }
                    }
                });
            });
        });
    };</script>
';
    }

    private function sign($value, $secret)
    {
        ksort($value);
        reset($value);
        $sign_param = implode('&', $value);
        $signature = hash_hmac('sha256', $sign_param, $secret, true);
        return base64_encode($signature);
    }
}
