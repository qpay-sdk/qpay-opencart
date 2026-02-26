<?php

namespace Opencart\Admin\Controller\Extension\Payment;

class QPay extends \Opencart\System\Engine\Controller {
    public function index(): void {
        $this->load->language('extension/payment/qpay');
        $this->document->setTitle($this->language->get('heading_title'));

        $data['breadcrumbs'] = [];
        $data['breadcrumbs'][] = ['text' => $this->language->get('text_home'), 'href' => $this->url->link('common/dashboard')];
        $data['breadcrumbs'][] = ['text' => $this->language->get('heading_title'), 'href' => $this->url->link('extension/payment/qpay')];

        if ($this->request->server['REQUEST_METHOD'] == 'POST') {
            $this->load->model('setting/setting');
            $this->model_setting_setting->editSetting('payment_qpay', $this->request->post);
            $this->session->data['success'] = $this->language->get('text_success');
            $this->response->redirect($this->url->link('extension/payment/qpay'));
        }

        $fields = ['status', 'base_url', 'username', 'password', 'invoice_code', 'callback_url', 'order_status_id', 'sort_order'];
        foreach ($fields as $field) {
            $data['payment_qpay_' . $field] = $this->request->post['payment_qpay_' . $field] ?? $this->config->get('payment_qpay_' . $field) ?? '';
        }

        $data['action'] = $this->url->link('extension/payment/qpay');
        $data['cancel'] = $this->url->link('marketplace/extension', 'type=payment');

        $this->load->model('localisation/order_status');
        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/payment/qpay', $data));
    }
}
