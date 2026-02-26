<?php

namespace Opencart\Catalog\Controller\Extension\Payment;

class QPay extends \Opencart\System\Engine\Controller {
    public function index(): string {
        $this->load->language('extension/payment/qpay');
        $data['action'] = $this->url->link('extension/payment/qpay.confirm');
        return $this->load->view('extension/payment/qpay', $data);
    }

    public function confirm(): void {
        $this->load->model('checkout/order');
        $order_id = $this->session->data['order_id'];
        $order = $this->model_checkout_order->getOrder($order_id);

        require_once DIR_SYSTEM . 'library/qpay.php';
        $qpay = new \QPay(
            $this->config->get('payment_qpay_base_url') ?: 'https://merchant.qpay.mn',
            $this->config->get('payment_qpay_username'),
            $this->config->get('payment_qpay_password')
        );

        $invoice = $qpay->createInvoice([
            'invoice_code' => $this->config->get('payment_qpay_invoice_code'),
            'sender_invoice_no' => (string) $order_id,
            'invoice_receiver_code' => $order['email'],
            'invoice_description' => 'Order #' . $order_id,
            'amount' => (float) $order['total'],
            'callback_url' => $this->config->get('payment_qpay_callback_url') ?: $this->url->link('extension/payment/qpay.callback'),
        ]);

        if ($invoice && !empty($invoice['invoice_id'])) {
            $this->session->data['qpay_invoice'] = $invoice;
            $json['redirect'] = $this->url->link('extension/payment/qpay.pay');
        } else {
            $json['error'] = 'QPay invoice creation failed';
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json ?? ['error' => 'Unknown error']));
    }

    public function pay(): void {
        $data['invoice'] = $this->session->data['qpay_invoice'] ?? [];
        $data['check_url'] = $this->url->link('extension/payment/qpay.check');
        $data['success_url'] = $this->url->link('checkout/success');

        $data['header'] = $this->load->controller('common/header');
        $data['footer'] = $this->load->controller('common/footer');
        $this->response->setOutput($this->load->view('extension/payment/qpay_pay', $data));
    }

    public function check(): void {
        $invoice_id = $this->session->data['qpay_invoice']['invoice_id'] ?? '';
        require_once DIR_SYSTEM . 'library/qpay.php';
        $qpay = new \QPay(
            $this->config->get('payment_qpay_base_url') ?: 'https://merchant.qpay.mn',
            $this->config->get('payment_qpay_username'),
            $this->config->get('payment_qpay_password')
        );
        $result = $qpay->checkPayment($invoice_id);
        $paid = !empty($result['rows']);

        if ($paid) {
            $this->load->model('checkout/order');
            $this->model_checkout_order->addHistory(
                $this->session->data['order_id'],
                (int) $this->config->get('payment_qpay_order_status_id'),
                'QPay payment confirmed',
                true
            );
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode(['paid' => $paid]));
    }

    public function callback(): void {
        $body = json_decode(file_get_contents('php://input'), true);
        $invoice_id = $body['invoice_id'] ?? '';
        // Webhook handling - verify and update order
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode(['status' => 'ok']));
    }
}
