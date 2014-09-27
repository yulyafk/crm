<?php

namespace OroCRM\Bundle\ChannelBundle\Tests\Unit\Entity;

use Carbon\Carbon;

use OroCRM\Bundle\ChannelBundle\Entity\LifetimeValueAverageAggregation;

class LifetimeValueAverageAggregationTest extends AbstractEntityTestCase
{
    /** @var LifetimeValueAverageAggregation */
    protected $entity;

    /**
     * {@inheritDoc}
     */
    public function getEntityFQCN()
    {
        return 'OroCRM\Bundle\ChannelBundle\Entity\LifetimeValueAverageAggregation';
    }

    /**
     * {@inheritDoc}
     */
    public function getDataProvider()
    {
        $channel         = $this->getMock('OroCRM\Bundle\ChannelBundle\Entity\Channel');
        $someDateTime    = new \DateTime();
        $someInteger     = 3;
        $someFloat       = 121.12;
        $aggregationDate = Carbon::createFromTimestampUTC(time());
        $aggregationDate->firstOfMonth();

        return [
            'amount'          => ['amount', $someFloat, $someFloat],
            'dataChannel'     => ['dataChannel', $channel, $channel],
            'month'           => ['month', $someInteger, $someInteger],
            'quarter'         => ['quarter', $someInteger, $someInteger],
            'year'            => ['year', $someInteger, $someInteger],
            'aggregationDate' => ['aggregationDate', $someDateTime, $aggregationDate],
        ];
    }

    public function testPrePersist()
    {
        $this->assertNull($this->entity->getAggregationDate());
        $this->assertNull($this->entity->getMonth());
        $this->assertNull($this->entity->getQuarter());
        $this->assertNull($this->entity->getYear());

        $this->entity->prePersist();

        $this->assertInstanceOf('DateTime', $this->entity->getAggregationDate());
        $this->assertNotEmpty($this->entity->getMonth());
        $this->assertNotEmpty($this->entity->getQuarter());
        $this->assertNotEmpty($this->entity->getYear());
    }
}
