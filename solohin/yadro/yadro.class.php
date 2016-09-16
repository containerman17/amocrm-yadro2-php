<?php
/**
 * Created by PhpStorm.
 * User: solohin
 * Date: 14.09.16
 * Time: 7:44
 */

namespace solohin\yadro;

class yadro
{
    use \solohin\SpeedTest;

    /** @var DataKeeperInterface */
    private $dataKeeper;
    /** @var \Psr\Log\AbstractLogger */
    private $logger;
    /** @var AmoDigger */
    private $amoDigger;

    public function __construct($domain, $login, $password, $verbose = false, $dataKeeper = 'MongoDataKeeper')
    {

        $keeperClass = __NAMESPACE__ . '\\' . $dataKeeper;

        $this->logger = new Logger('yadro-' . date('Y-m-d') . '.txt', $verbose, $domain);
        $this->dataKeeper = new $keeperClass($domain, $this->logger);
        $this->amoDigger = new AmoDigger($domain, $login, $password, $this->logger);
    }

    public function updateAll()
    {
        $this->startTimer('Total execution time');

        $items = ['leads', 'contacts', 'account', 'notes_contact', 'notes_lead', 'notes_company', 'notes_task', 'tasks', 'companies'];
        foreach($items as $item){
            $this->update($item);
        }

        $this->logger->info('RAM used: '.(round(memory_get_peak_usage(true)/1024/1024, 2)).' MB.');
        $this->logger->info('Total execution time: ~'.round($this->stopTimer('Total execution time')).' seconds.');
        $this->logger->info('Bye-bye!');
    }

    public function update($what)
    {
        $ts = $this->dataKeeper->getLastTimestamp($what);

        $this->startTimer($what);
        $data = $this->amoDigger->get($what, $ts);

        $this->logger->info(
            sprintf(
                '%s items fetched from %s collection starting from %s. It took %.2f sec.',
                count($data),
                $what,
                date("Y-m-d H:i:s", $ts),
                $this->stopTimer($what)
            )
        );

        $this->dataKeeper->upsertBatch($what, $data);
    }
}