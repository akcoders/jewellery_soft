<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Jewellery extends BaseConfig
{
    /**
     * Lead pipeline stage list.
     *
     * @var list<string>
     */
    public array $leadStages = [
        'New',
        'Contacted',
        'Qualified',
        'Proposal Sent',
        'Negotiation',
        'Won',
        'Lost',
    ];

    /**
     * Order workflow status list.
     *
     * @var list<string>
     */
    public array $orderStatuses = [
        'Confirmed',
        'In Production',
        'QC',
        'Ready',
        'Packed',
        'Dispatched',
        'Completed',
    ];

    /**
     * Order priority list.
     *
     * @var list<string>
     */
    public array $orderPriorities = [
        'Low',
        'Medium',
        'High',
        'Urgent',
    ];
}
