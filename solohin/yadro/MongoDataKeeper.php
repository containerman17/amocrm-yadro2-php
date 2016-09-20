<?php
/**
 * Created by PhpStorm.
 * User: solohin
 * Date: 14.09.16
 * Time: 7:58
 */
namespace solohin\yadro;

use MongoDB;
use MongoClient;
use Psr\Log\AbstractLogger;

class MongoDataKeeper implements DataKeeperInterface
{
    /** @var MongoDB */
    private $db;
    private $addIndexes = [
        'default' => [
            ['keys' => ['id' => -1], 'options' => ['unique' => true, 'background' => true]],
            ['keys' => ['date_create' => -1], 'options' => ['background' => true]],
            ['keys' => ['last_modified' => -1], 'options' => ['background' => true]],
            ['keys' => ['responsible_user_id' => 1], 'options' => ['background' => true]],
            ['keys' => ['created_user_id' => 1], 'options' => ['background' => true]],
        ],
        'notes' => [
            ['keys' => ['element_id' => 1], 'options' => ['background' => true]],
            ['keys' => ['element_type' => 1], 'options' => ['background' => true]],
        ],
        'leads' => [
            ['keys' => ['status_id' => 1], 'options' => ['background' => true]],
            ['keys' => ['pipeline_id' => 1], 'options' => ['background' => true]],
        ],
        'tasks' => [
            ['keys' => ['element_id' => 1], 'options' => ['background' => true]],
            ['keys' => ['element_type' => 1], 'options' => ['background' => true]],
            ['keys' => ['complete_till' => 1], 'options' => ['background' => true]],
            ['keys' => ['status' => 1], 'options' => ['background' => true]],
        ],
    ];
    private $disableIndexing = ['account'];
    private $logger;
    private $collectionsOverride = [
        'notes_contact' => 'notes',
        'notes_lead' => 'notes',
        'notes_company' => 'notes',
        'notes_task' => 'notes',
        'companies' => 'company',
    ];

    /**
     * DataKeeperInterface constructor.
     * @param $accountName
     * @param $logger
     */
    public function __construct($accountName, AbstractLogger $logger)
    {
        $this->db = new MongoDB(new MongoClient(), '__' . $accountName);
        $this->logger = $logger;
    }

    /**
     * @param $name
     * return MongoCollection
     */
    private function getCollection($name)
    {
        if (!in_array($name, $this->db->getCollectionNames())) {
            $this->db->createCollection($name);

            $this->logger->warning('Collection ' . $name . ' not found. Creating a new one.');


            if (!in_array($name, $this->disableIndexing)) {
                $indexes = $this->addIndexes['default'];

                if (isset($this->addIndexes[$name])) {
                    $indexes = array_merge($indexes, $this->addIndexes[$name]);
                }

                foreach ($indexes as $index) {
                    $this->db->{$name}->createIndex($index['keys'], $index['options']);
                    $this->logger->info(
                        sprintf(
                            'Creating indexes %s for collection %s',
                            implode(', ', array_keys($index['keys'])),
                            $name
                        )
                    );
                }
            }
        }
        return $this->db->{$name};
    }

    /**
     * Обновляет запись или вставляет новую
     * @param $type
     * @param array $data
     * @return mixed
     */
    public function upsertBatch($type, array $data)
    {
        if (isset($this->collectionsOverride[$type])) {
            $type = $this->collectionsOverride[$type];
        }

        $collection = $this->getCollection($type);
        foreach ($data as $item) {
            $item['id'] = ''.intval($item['id']);

            if(isset($item['deleted']) && $item['deleted'] == '1'){
                $collection->remove(['id' => $item['id']]);
            }else{
                $collection->update(
                    ['id' => $item['id']],
                    $item,
                    ['upsert' => true]
                );
            }
        }
    }

    /**
     * Получает самую большую дату изменения объекта
     * @param $type
     * @return mixed
     */
    public function getLastTimestamp($type)
    {
        if (in_array($type, ['notes_contact', 'notes_lead', 'notes_task', 'notes_company'])) {
            return $this->getLastNotesTimestamp($type);
        }
        $collection = $this->getCollection($type);
        $cursor = $collection->find()->sort(['last_modified' => -1])->limit(1);
        $items = array_values(iterator_to_array($cursor));
        if (isset($items[0]['last_modified'])) {
            return $items[0]['last_modified'];
        } else {
            return 0;
        }
    }

    private function getLastNotesTimestamp($type)
    {
        $collection = $this->getCollection('notes');

        $filter = [];

        if ($type == 'notes_contact') {
            $filter['element_type'] = '' . AmoDigger::ELEMENT_TYPE_CONTACT;
        } elseif ($type == 'notes_lead') {
            $filter['element_type'] = '' . AmoDigger::ELEMENT_TYPE_LEAD;
        } elseif ($type == 'notes_task') {
            $filter['element_type'] = '' . AmoDigger::ELEMENT_TYPE_TASK;
        } elseif ($type == 'notes_company') {
            $filter['element_type'] = '' . AmoDigger::ELEMENT_TYPE_COMPANY;
        } else {
            throw new \Exception('Тип индексов ' . $type . ' не поддерживается');
        }

        $cursor = $collection->find($filter)->sort(['last_modified' => -1])->limit(1);
        $items = array_values(iterator_to_array($cursor));
        if (isset($items[0]['last_modified'])) {
            return $items[0]['last_modified'];
        } else {
            return 0;
        }
    }
}