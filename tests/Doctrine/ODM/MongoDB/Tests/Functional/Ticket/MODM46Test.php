<?php

declare(strict_types=1);

namespace Doctrine\ODM\MongoDB\Tests\Functional\Ticket;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;
use Doctrine\ODM\MongoDB\Tests\BaseTest;
use MongoDB\BSON\ObjectId;

class MODM46Test extends BaseTest
{
    public function testTest()
    {
        $a = [
            '_id' => new ObjectId(),
            'c' => ['value' => 'value'],
        ];
        $this->dm->getDocumentCollection(__NAMESPACE__ . '\MODM46A')->insertOne($a);

        $a = $this->dm->find(__NAMESPACE__ . '\MODM46A', $a['_id']);

        $this->assertTrue(isset($a->b));
        $this->assertEquals('value', $a->b->value);
    }
}

/** @ODM\Document */
class MODM46A
{
    /** @ODM\Id */
    public $id;

    /**
     * @ODM\EmbedOne(targetDocument="MODM46AB")
     * @ODM\AlsoLoad("c")
     */
    public $b;
}

/** @ODM\EmbeddedDocument */
class MODM46AB
{
    /** @ODM\Field(type="string") */
    public $value;
}
