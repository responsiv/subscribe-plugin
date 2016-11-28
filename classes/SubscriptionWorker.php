<?php namespace Responsiv\Subscribe\Classes;

use Carbon\Carbon;
use Responsiv\Pay\Models\Invoice as InvoiceModel;
use Responsiv\Pay\Models\InvoiceStatusLog;
use Responsiv\Subscribe\Models\Status as StatusModel;
use Responsiv\Subscribe\Models\Membership as MembershipModel;
use Responsiv\Subscribe\Models\Schedule as ScheduleModel;
use ApplicationException;
use Exception;

/**
 * Worker class, engaged by the automated worker
 */
class SubscriptionWorker
{
    use \October\Rain\Support\Traits\Singleton;

    /**
     * @var Responsiv\Campaign\Classes\SubscriptionManager
     */
    protected $subscriptionManager;

    /**
     * @var bool There should be only one task performed per execution.
     */
    protected $isReady = true;

    /**
     * @var string Processing message
     */
    protected $logMessage = 'There are no outstanding activities to perform.';

    /**
     * Initialize this singleton.
     */
    protected function init()
    {
        $this->subscriptionManager = SubscriptionManager::instance();
    }

    /*
     * Process all tasks
     */
    public function process()
    {
        $this->isReady && $this->generateInvoices();

        return $this->logMessage;
    }

    /**
     * Generate Invoices
     */
    public function generateInvoices()
    {
        $result = [
            'itemsTotal' => 0,
            'itemsAnalyzed' => 0,
            'itemsCancelled' => 0,
            'itemsActivated' => 0,
            'itemsCompleted' => 0,
            'itemsRenewed' => 0,
            'messages' => [],
            'errorMessage' => ''
        ];

        $this->log('Generate Invoices Start ===============');

        /*
         * Process invoices
         */
        try {
            $memberships = MembershipModel::lists('id');

            $result['itemsTotal'] = count($memberships);

            $this->log(sprintf('Memberships to process: %s', $result['itemsTotal']));

            $batchSize = 100;

            $this->log(sprintf('Batch Size: %s', $batchSize));

            $row = 0;
            $offset = 0;
            do {
                $batchIds = array_slice($memberships, $offset, $batchSize);

                if ($batchIds) {
                    $offset += $batchSize;
                    $idStr = implode(',', $batchIds);

                    $this->log(sprintf('Batch Group %s: %s', $row + 1, implode(', ', $batchIds)));

                    $result = $this->generateInvoicesBatch($idStr, $result);

                    if (!empty($result['errorMessage'])) {
                        throw new ApplicationException($result['errorMessage']);
                    }
                }

                $row++;

            } while ($batchIds);

        }
        catch (Exception $ex) {
            $this->log(sprintf('Error: %s', $ex->getMessage()), true);

            $result['errorMessage'] = $ex->getMessage();

            throw $ex;
        }

        $this->log(str_repeat('-', 30));
        $this->log(sprintf('Total: %s', $result['itemsTotal']));
        $this->log(sprintf('Analysed: %s', $result['itemsAnalyzed']));
        $this->log(sprintf('Cancelled: %s', $result['itemsCancelled']));
        $this->log(sprintf('Activated: %s', $result['itemsActivated']));
        $this->log(sprintf('Completed: %s', $result['itemsCompleted']));
        $this->log(sprintf('Renewed: %s', $result['itemsRenewed']));

        $this->log($this->formatResult($result));

        $this->log('Generate Invoices End ===============');

        return $result;
    }

    /*
     * Generate invoices in batching
     */
    public function generateInvoicesBatch($idStr, $result = [])
    {
        $result = array_merge([
            'itemsTotal' => 0,
            'itemsAnalyzed' => 0,
            'itemsCancelled' => 0,
            'itemsActivated' => 0,
            'itemsCompleted' => 0,
            'itemsRenewed' => 0,
            'messages' => [],
            'errorMessage' => ''
        ], $result);

        $rawIds = explode(',', $idStr);
        $ids = [];
        foreach($rawIds as $id) {
            if (strlen($id = trim($id))) {
                $ids[] = $id;
            }
        }

        if (!$ids) {
            return;
        }

        $allMembership = MembershipModel::whereIn('id', $ids)->get();

        foreach ($allMembership as $membership) {
            try
            {
                $result['itemsAnalyzed']++;

                $this->log(sprintf('Analyze Membership # %s', $membership->id));

                $checkDate = Carbon::now();

                /*
                 * Membership cancelled
                 */
                if ($membership->cancelled_at) {
                    $this->log('Already Cancelled');
                }
                /*
                 * Should membership be cancelled
                 */
                else if (
                    $membership->delay_cancelled_at &&
                    $membership->delay_cancelled_at <= $checkDate
                ) {
                    /*
                     * Cancel membership
                     */
                    $membership->cancelMembership(null, null, 'Cancelled on delayed cancellation date');

                    $result['itemsCancelled']++;
                    $result['messages'][] = sprintf('Membership # %s: Cancelled - Delayed', $membership->id);

                    $this->log(sprintf(
                        'Cancelled - Delay cancellation date: %s',
                        is_object($membership->delay_cancelled_at)
                            ? $membership->delay_cancelled_at->toFormattedDateString()
                            : $membership->delay_cancelled_at
                    ));
                }
                /*
                 * Start in the future
                 */
                else if (
                    $membership->delay_activated_at &&
                    $membership->delay_activated_at > $checkDate
                ) {
                    /*
                     * Do nothing
                     */
                }
                /*
                 * Membership already started
                 */
                else if (
                    $membership->delay_activated_at &&
                    $membership->delay_activated_at <= $checkDate
                ) {
                    $this->log('Membership should be activated now.');

                    if ($invoice = $membership->invoice) {
                        $membership->receivePayment($invoice);
                        $this->subscriptionManager->configureMembership($membership, $invoice);
                    }
                    else {
                        $this->log('Membership is missing an invoice!', true);
                    }
                }
                /*
                 * Mmbership interval end
                 */
                else if (
                    $membership->plan->renewal_period &&
                    $membership->plan->renewal_period >= $membership->renewal_period
                ) {
                    /*
                     * Finish membership
                     */
                    $membership->completeMembership('Renewal period reached');

                    $result['itemsCompleted']++;
                    $result['messages'][] = sprintf('Membership # %s: Completed - Renewal Period', $membership->id);

                    $this->log(sprintf('Complete - Renewal period reached: %s', $membership->renewal_period));
                }
                /*
                 * Membership up for renewal
                 */
                else if (
                    $membership->current_period_end &&
                    $membership->current_period_end <= $checkDate
                ) {
                    $graceStatus = Status::getStatusGrace();
                    $trialStatus = Status::getStatusTrial();
                    $completeStatus = Status::getStatusComplete();

                    if ($membership->status_id == $graceStatus->id) {
                        /*
                         * Grace, expire
                         */
                        $membership->noPayment('Grace ended');

                        $result['itemsCompleted']++;
                        $result['messages'][] = sprintf('Membership # %s: Past due from grace', $membership->id);

                        $this->log('Past due - From grace');
                    }
                    else if ($membership->status_id == $trialStatus->id) {
                        /*
                         * Trial, expire
                         */
                        $membership->noPayment('Trial ended');

                        $result['itemsCompleted']++;
                        $result['messages'][] = sprintf('Membership # %s: Past due from trial', $membership->id);

                        $this->log('Past due - From trial');
                    }
                    else if ($membership->status_id == $completeStatus->id) {
                        /*
                         * Do nothing
                         */
                    }
                    else {
                        $maxloop = 50;
                        $loop = 0;

                        /*
                         * Loop periods, get them caught up to date
                         */
                        do {
                            $current = true;
                            $loop++;

                            $unpaidInvoice = Invoice::applyUnpaid()
                                ->applyRelated($membership)
                                ->where('id', '!=', $invoice->id)
                                ->count()
                            ;

                            if ($unpaidInvoice) {
                                $renewed = false;
                            }
                            else {
                                /*
                                 * Renew memebership, if able
                                 */
                                $renewed = $membership->renewMembership();
                            }

                            if ($renewed) {
                                $this->log('Start Membership Renewal');

                                if (!$membership->invoice_item) {
                                    throw new ApplicationException(sprintf('Invoice item ID #%s not found.', $membership->invoice_item_id));
                                }

                                $invoice = $this->createInvoice($membership);
                                $invoice = $invoice->reload();

                                /*
                                 * If invoice is for $0, process a fake payment
                                 */
                                if ($invoice->total <= 0) {
                                    $invoice->submitManualPayment('Invoice total is zero');
                                }

                                $result['itemsRenewed']++;
                                $result['messages'][] = sprintf(
                                    'Membership # %s: Renewed - Invoice #: %s for period %s (%s - %s)',
                                    $membership->id,
                                    $invoice->id,
                                    $membership->renewal_period,
                                    $membership->current_period_start->toFormattedDateString(),
                                    $membership->current_period_end->toFormattedDateString()
                                );

                                $this->log(sprintf('Renewed - New Invoice #%s', $invoice->id));
                                $this->log(sprintf('Billing Period: %s', $membership->renewal_period));
                                $this->log(sprintf('New Period Start: %s', $membership->current_period_start->toFormattedDateString()));
                                $this->log(sprintf('New Period End: %s', $membership->current_period_end->toFormattedDateString()));

                                /*
                                 * Membership is not current
                                 */
                                if ($membership->current_period_end && $membership->current_period_end <= $checkDate) {
                                    $current = false;

                                    $this->log('Membership is not current, continuing');
                                }
                            }
                            else {
                                /*
                                 * Grace period without grace status
                                 */
                                if ($membership->plan->grace_period && $membership->status->id != $graceStatus->id) {
                                    $membership->startGracePeriod();

                                    $result['messages'][] = sprintf('Membership # %s: Graced', $membership->id);

                                    $this->log(sprintf('Grace - Current period end was reached: %s', $membership->current_period_end));
                                }
                                else if ($unpaidInvoice) {
                                    /*
                                     * Do nothing
                                     */
                                }
                                else {
                                    /*
                                     * Finish membership
                                     */
                                    $membership->completeMembership('Does not renew');

                                    $result['itemsCompleted']++;
                                    $result['messages'][] = sprintf('Membership # %s: Completed - Does not renew', $membership->id);

                                    $this->log('Complete - Membership could not renew');
                                }
                            }

                            /*
                             * Prevent recursion
                             */
                            if ($loop >= $maxloop) {
                                break;
                            }
                        }
                        while(!$current);
                    }
                }
                /*
                 * Does not match requirements
                 */
                else {
                    $this->log('Nothing needs to happen for this membership');
                }
            }
            catch (Exception $ex) {
                $result['messages'][] = sprintf('Membership # %s: Error: %s', $membership->id, $ex->getMessage());

                $this->log(sprintf('Error: %s', $ex->getMessage()));

                throw $ex;
            }
        }

        return $result;
    }

    /**
     * Raise a new invoice for a membership.
     */
    protected function createInvoice($membership)
    {
        $price = null;
        $comment = null;

        /*
         * Price adjustment
         */
        $adjustment = ScheduleModel::where('membership_id', $membership->id)
            ->where('billing_period', $membership->renewal_period)
            ->first();

        if ($adjustment) {
            $comment = $adjustment->comment;
            $price = $adjustment->price;
            $this->log(sprintf('Schedule found - Price: %s', $price));
        }

        /*
         * Replicate invoice
         */
        $invoice = $membership->invoice->replicate();
        $invoice->reloadRelations();
        $invoice->due_at = $invoice->freshTimestamp();
        $invoice->save();

        /*
         * Replicate invoice item
         */
        $item = $membership->invoice_item->replicate();
        $item->reloadRelations();
        $item->invoice = $invoice;

        if ($price !== null) {
            $item->price = $price;
        }

        $item->save();
        $invoice->touchTotals();
        $this->log(sprintf('Item Price: %s', $item->price));

        return $invoice;
    }

    /*
     * Internal
     */

    /*
     * Format membership invoice generation
     */
    public static function formatResult($resultObj)
    {
        $result = [];

        if ($resultObj['errorMessage']) {
            $result[] = 'Error: '.$resultObj['errorMessage'];
        }

        $result[] = 'Memberships total: '.$resultObj['itemsTotal'];
        $result[] = 'Memberships analyzed: '.$resultObj['itemsAnalyzed'];
        $result[] = 'Memberships cancelled: '.$resultObj['itemsCancelled'];
        $result[] = 'Memberships activated: '.$resultObj['itemsActivated'];
        $result[] = 'Memberships completed: '.$resultObj['itemsCompleted'];
        $result[] = 'Memberships renewed: '.$resultObj['itemsRenewed'];

        foreach ($resultObj['messages'] as $message) {
            $result[] = $message;
        }

        return implode(PHP_EOL, $result);
    }
    
    /*
     * Outputs to log
     */
    protected function log($str, $error = false)
    {
        echo $str . "\n";
        traceLog($str);

        if ($error) {
            // @todo send email?
        }
    }
}
