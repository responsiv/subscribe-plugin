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

    }

}
