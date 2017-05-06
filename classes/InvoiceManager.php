<?php namespace Responsiv\Subscribe\Classes;

use Db;
use Carbon\Carbon;
use Responsiv\Subscribe\Models\Status as StatusModel;
use Responsiv\Subscribe\Models\StatusLog as StatusLogModel;
use Responsiv\Subscribe\Models\Service as ServiceModel;
use Responsiv\Subscribe\Models\Setting as SettingModel;
use Responsiv\Pay\Models\Invoice as InvoiceModel;
use Responsiv\Pay\Models\InvoiceItem as InvoiceItemModel;
use Responsiv\Pay\Models\InvoiceStatus as InvoiceStatusModel;
use Exception;

/**
 * Invoice engine
 */
class InvoiceManager
{
    use \October\Rain\Support\Traits\Singleton;

    /**
     * @var Carbon\Carbon
     */
    public $now;

    /**
     * Initialize this singleton.
     */
    protected function init()
    {
        $this->now = Carbon::now();
    }

    //
    // Invoicing
    //

    public function attemptAutomaticPayment(InvoiceModel $invoice)
    {
        /*
         * If invoice is for $0, process a fake payment
         */
        if ($invoice->total <= 0) {
            $invoice->submitManualPayment('Invoice total is zero');

            return true;
        }
        else {
            /*
             * Pay from profile
             */
            try {
                if (!$paymentMethod = $invoice->payment_method) {
                    throw new Exception('Invoice is missing a payment method!');
                }

                return true;
            }
            /*
             * Payment failed
             */
            catch (Exception $ex) {
                return false;
            }
        }
    }

    public function raiseServiceRenewalInvoice(ServiceModel $service)
    {
        $invoice = $this->raiseServiceInvoice($service);

        $this->raiseServiceInvoiceItem($invoice, $service);

        $invoice->updateInvoiceStatus(InvoiceStatusModel::STATUS_APPROVED);

        $invoice->touchTotals();

        $invoice->reload();

        return $invoice;
    }

    public function raiseServiceInvoice(ServiceModel $service)
    {
        if (!$service->exists) {
            throw new ApplicationException('Please create the service before initialization');
        }

        if (!$user = $service->user) {
            throw new ApplicationException('Service is missing a user!');
        }

        $invoice = InvoiceModel::applyUnpaid()->applyUser($user)->applyRelated($service);

        if ($service->is_throwaway) {
            $invoice->applyThrowaway();
        }

        $invoice = $invoice->first() ?: InvoiceModel::makeForUser($user);
        $invoice->is_throwaway = $service->is_throwaway;
        $invoice->related = $service;
        $invoice->save();

        return $invoice;
    }

    public function raiseServiceSetupFee(InvoiceModel $invoice, ServiceModel $service, $price)
    {
        $item = new InvoiceItemModel;
        $item->invoice = $invoice;
        $item->quantity = 1;
        $item->price = $price;
        $item->description = 'Set up fee';
        $item->save();

        return $item;
    }

    /**
     * Populates an invoices items, returns the primary item.
     */
    public function raiseServiceInvoiceItem(InvoiceModel $invoice, ServiceModel $service)
    {
        if (!$plan = $service->plan) {
            throw new ApplicationException('Service is missing a plan!');
        }

        $item = InvoiceItemModel::applyRelated($service)
            ->applyInvoice($invoice)
            ->first()
        ;

        if ($item) {
            return $item;
        }

        $item = new InvoiceItemModel;
        $item->invoice = $invoice;
        $item->quantity = 1;
        $item->tax_class_id = $plan->tax_class_id;
        $item->price = $this->getPriceForService($service);
        $item->description = $plan->name;
        $item->related = $service;
        $item->save();

        return $item;
    }

    public function voidUnpaidService(ServiceModel $service)
    {
        $invoices = InvoiceModel::applyUnpaid()->applyRelated($service)->get();

        foreach ($invoices as $invoice) {
            $invoice->updateInvoiceStatus(InvoiceStatusModel::STATUS_VOID);
        }
    }

    public function getPriceForService(ServiceModel $service)
    {
        if (!$plan = $service->plan) {
            throw new ApplicationException('Service is missing a plan!');
        }

        // @todo Look up scheduled pricing here
        $price = $service->price ?: $plan->price;

        /*
         * First invoice, prorate the price
         */
        $firstInvoice = $service->count_renewal <= 0;
        if ($firstInvoice) {
            $startDate = clone $this->now;

            /*
             * Prorate from the trial end
             */
            if (
                $plan->hasTrialPeriod() &&
                ($membership = $service->membership) &&
                $membership->isTrialActive()
            ) {
                $startDate = $membership->trial_period_end;
            }

            $price = $plan->adjustPrice($price, $startDate);
        }

        return $price;
    }

    //
    // Invoicing
    //

    public function raiseMembershipFee(Invoice $invoice, Membership $membership, $price)
    {
        $item = InvoiceItemModel::applyRelated($membership)
            ->applyInvoice($invoice)
            ->first()
        ;

        if (!$item) {
            $item = new InvoiceItemModel;
            $item->invoice = $invoice;
            $item->related = $membership;
            $item->quantity = 1;
            $item->price = $price;
            $item->description = 'Membership fee';
            $item->save();
        }

        return $item;
    }
}
