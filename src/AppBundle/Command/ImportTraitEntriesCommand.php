<?php

namespace AppBundle\Command;


use AppBundle\Entity\TraitCategoricalEntry;
use AppBundle\Entity\TraitCategoricalValue;
use AppBundle\Entity\TraitCitation;
use AppBundle\Entity\TraitNumericalEntry;
use AppBundle\Entity\TraitType;
use AppBundle\Entity\Webuser;
use Doctrine\ORM\EntityManager;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\ParameterBag;

class ImportTraitEntriesCommand extends ContainerAwareCommand
{
    const BATCH_SIZE = 10;
    /**
     * @var EntityManager
     */
    private $em;

    /**
     * @var array<int> TraitType ids
     */
    private $traitType;

    /**
     * @var array<string> traitFormats
     */
    private $traitFormat;

    /**
     * @var int
     */
    private $insertedCitations = 0;

    /**
     * @var int
     */
    private $insertedValues = 0;

    /**
     * @var int
     */
    private $insertedEntries = 0;

    /**
     * @var int
     */
    private $skippedNoHit = 0;

    /**
     * @var int
     */
    private $skippedMultiHits = 0;

    /**
     * @var array
     */
    private $mapping;

    /**
     * @var string
     */
    private $connectionName;

    protected function configure()
    {
        $this
        // the name of the command (the part after "bin/console")
        ->setName('app:import-trait-entries')

        // the short description shown while running "php bin/console list"
        ->setDescription('Importer for trait values.')

        // the full command description shown when running the command with
        // the "--help" option
        ->setHelp("This command allows you to create trait types...\n".
            "The tsv file has to have the following columns (only the first two are required to have a value):\n".
            "fennec_id\tvalue\tvalue_ontology\tcitation\torigin_url\n\n".
            "or with --long-table option:\n".
            "#FennecID\tTraitType1\tTraitType2\t...\n".
            "fennec_id\tTraitValue1\tTraitValue2\t...\n"
        )
        ->addArgument('file', InputArgument::REQUIRED, 'The path to the input csv file')
        ->addOption('connection', 'c', InputOption::VALUE_REQUIRED, 'The database version')
        ->addOption('traittype', 't', InputOption::VALUE_REQUIRED, 'The name of the trait type', null)
        ->addOption('user-id', "u", InputOption::VALUE_REQUIRED, 'ID of the user importing the data', null)
        ->addOption('default-citation', null, InputOption::VALUE_REQUIRED, 'Default citation to use if not explicitly set in the citation column of the tsv file', null)
        ->addOption('mapping', "m", InputOption::VALUE_REQUIRED, 'Method of mapping for id column. If not set fennec_ids are assumed and no mapping is performed', null)
        ->addOption('public', 'p', InputOption::VALUE_NONE, 'import traits as public (default is private)')
        ->addOption('skip-unmapped', 's', InputOption::VALUE_NONE, 'do not exit if a line can not be mapped (uniquely) to a fennec_id instead skip this entry', null)
        ->addOption('long-table', null, InputOption::VALUE_NONE, 'The format of the table is long table', null)
    ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln([
            'TraitValues Importer',
            '====================',
            '',
        ]);
        if(!$this->checkOptions($input, $output)){
            return;
        }
        $this->initConnection($input);
        // Logger has to be disabled, otherwise memory increases linearly
        $this->em->getConnection()->getConfiguration()->setSQLLogger(null);
        gc_enable();
        $user = $this->em->getRepository('AppBundle:Webuser')->find($input->getOption('user-id'));
        if($user === null){
            $output->writeln('<error>User with provided id does not exist in db.</error>');
            return;
        }
        $userID = $user->getWebuserId();
        $lines = intval(exec('wc -l '.escapeshellarg($input->getArgument('file')).' 2>/dev/null'));
        $progress = new ProgressBar($output, $lines);
        $progress->start();
        $needs_mapping = $input->getOption('mapping') !== null;
        if($needs_mapping) {
            $this->mapping = $this->getMapping($input->getArgument('file'), $input->getOption('mapping'),
                $input->getOption('long-table'));
            if (!$input->getOption('skip-unmapped')) {
                foreach ($this->mapping as $id => $value) {
                    if ($value === null) {
                        $output->writeln('<error>Error no mapping to fennec id found for: ' . $id . '</error>');
                        return;
                    } elseif (is_array($value)) {
                        $output->writeln('<error>Error multiple mappings to fennec ids found for: ' . $id . ' (' . implode(',',
                                $value) . ')</error>');
                        return;
                    }
                }
            }
        }
        $file = fopen($input->getArgument('file'), 'r');
        $traitTypes = array($input->getOption('traittype'));
        if($input->getOption('long-table')){
            $line = fgetcsv($file, 0, "\t");
            $traitTypes = array_slice($line, 1);
        }
        if(!$this->checkTraitTypes($traitTypes, $output)){
            return;
        }
        $this->em->getConnection()->beginTransaction();
        try{
            $i = 0;
            while (($line = fgetcsv($file, 0, "\t")) != false) {
                $fennec_id = $line[0];
                if($needs_mapping){
                    $fennec_id = $this->mapping[$fennec_id];
                    if($fennec_id === null){
                        ++$this->skippedNoHit;
                        $progress->advance();
                        continue;
                    } elseif (is_array($fennec_id)){
                        ++$this->skippedMultiHits;
                        $progress->advance();
                        continue;
                    }
                }
                if($input->getOption('long-table')){
                    $citationText = $input->getOption('default-citation');
                    if($citationText === null){
                        $citationText = "";
                    }
                    for($i=1; $i<count($line); $i++){
                        if($line[$i] !== ''){
                            $this->insertTraitEntry($fennec_id, $this->traitType[$i-1], $this->traitFormat[$i-1], $line[$i], '', $citationText, $userID, '', $input->getOption('public'));
                        }
                    }
                } else {
                    if(count($line) !== 5){
                        throw new \Exception('Wrong number of elements in line. Expected: 5, Actual: '.count($line).': '.join(" ",$line));
                    }
                    $citationText = $line[3];
                    if ($citationText === "" && $input->hasOption('default-citation')) {
                        $citationText = $input->getOption('default-citation');
                    }
                    $this->insertTraitEntry($fennec_id, $this->traitType[0], $this->traitFormat[0], $line[1], $line[2], $citationText, $userID,
                        $line[4], $input->getOption('public'));
                }
                $progress->advance();
                $i++;
                if($i % ImportTraitEntriesCommand::BATCH_SIZE === 0){
                    $this->em->flush();
                    $this->em->clear();
                    gc_collect_cycles();
                }
            }
            $this->em->flush();
            $this->em->getConnection()->commit();
        } catch (\Exception $e){
            $this->em->getConnection()->rollBack();
            $output->writeln('<error>'.$e->getMessage().'</error>');
            return;
        }
        fclose($file);
        $progress->finish();
        $output->writeln('');
        $table = new Table($output);
        $table->addRow(array('Imported entries', $this->insertedEntries));
        $table->addRow(array('Distinct new values', $this->insertedValues));
        $table->addRow(array('Distinct new citations', $this->insertedCitations));
        $table->addRow(array('Skipped (no hit)', $this->skippedNoHit));
        $table->addRow(array('Skipped (multiple hits)', $this->skippedMultiHits));
        $table->render();
    }

    private function get_or_insert_trait_categorical_value($value, $ontology_url, $traitType){
        $traitCategoricalValue = $this->em->getRepository('AppBundle:TraitCategoricalValue')->findOneBy(array(
            'traitType' => $traitType,
            'value' => $value,
            'ontologyUrl' => $ontology_url
        ));
        if($traitCategoricalValue === null){
            $traitCategoricalValue = new TraitCategoricalValue();
            $traitCategoricalValue->setTraitType($this->em->getReference('AppBundle:TraitType', $traitType));
            $traitCategoricalValue->setValue($value);
            $traitCategoricalValue->setOntologyUrl($ontology_url);
            $this->em->persist($traitCategoricalValue);
            $this->em->flush();
            ++$this->insertedValues;
        }
        return $traitCategoricalValue;
    }

    private function get_or_insert_trait_citation($citation){
        $traitCitation = $this->em->getRepository('AppBundle:TraitCitation')->findOneBy(array(
            'citation' => $citation
        ));
        if($traitCitation === null){
            $traitCitation = new TraitCitation();
            $traitCitation->setCitation($citation);
            $this->em->persist($traitCitation);
            $this->em->flush();
            ++$this->insertedCitations;
        }
        return $traitCitation;
    }

    private function getMapping($filename, $method, $skip_first_line = false){
        $ids = array();
        $file = fopen($filename, 'r');
        if($skip_first_line){
            fgetcsv($file, 0, "\t");
        }
        while (($line = fgetcsv($file, 0, "\t")) != false) {
            $ids[] = $line[0];
        }
        fclose($file);
        if($method === 'scientific_name'){
            $mapper = $this->getContainer()->get('app.api.webservice')->factory('mapping', 'byOrganismName');
            $mapping = $mapper->execute(new ParameterBag(array(
                'ids' => array_values(array_unique($ids)),
                'dbversion' => $this->connectionName
            )), null);
        } else {
            $mapper = $this->getContainer()->get('app.api.webservice')->factory('mapping', 'byDbxrefId');
            $mapping = $mapper->execute(new ParameterBag(array(
                'ids' => array_values(array_unique($ids)),
                'dbversion' => $this->connectionName,
                'db' => $method
            )), null);
        }
        return $mapping;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return boolean
     */
    protected function checkOptions(InputInterface $input, OutputInterface $output)
    {
        if ($input->getOption('traittype') === null && !$input->getOption('long-table')) {
            $output->writeln('<error>No trait type given. Use --traittype or set --long-table</error>');
            return false;
        }
        if ($input->getOption('user-id') === null) {
            $output->writeln('<error>No user ID given. Use --user-id</error>');
            return false;
        }
        if(!file_exists($input->getArgument('file'))){
            $output->writeln('<error>File does not exist: '.$input->getArgument('file').'</error>');
            return false;
        }
        return true;
    }

    /**
     * @param InputInterface $input
     */
    protected function initConnection(InputInterface $input)
    {
        $this->connectionName = $input->getOption('connection');
        if ($this->connectionName === null) {
            $this->connectionName = $this->getContainer()->get('doctrine')->getDefaultConnectionName();
        }
        $orm = $this->getContainer()->get('app.orm');
        $this->em = $orm->getManagerForVersion($this->connectionName);
    }

    /**
     * @param array $traitTypes
     * @param OutputInterface $output
     * @return boolean
     */
    protected function checkTraitTypes(array $traitTypes, OutputInterface $output)
    {
        $this->traitType = array();
        $this->traitFormat = array();
        foreach ($traitTypes as $type){
            $traitType = $this->em->getRepository('AppBundle:TraitType')->findOneBy(array('type' => $type));
            if ($traitType === null) {
                $output->writeln('<error>TraitType does not exist in db: "'.$type.'". Check for typos or create with app:create-traittype.</error>');
                return false;
            }
            else{
                $this->traitType[] = $traitType->getId();
                $this->traitFormat[] = $traitType->getTraitFormat()->getFormat();
            }
        }
        return true;
    }

    /**
     * @param int $fennec_id
     * @param TraitType $traitType
     * @param string $value
     * @param string|null $valueOntology
     * @param string $citation
     * @param int $userID webuser_id
     * @param string $originURL
     * @param boolean $public
     */
    protected function insertTraitEntry($fennec_id, $traitType, $traitFormat, $value, $valueOntology, $citation, $userID, $originURL, $public)
    {
        $traitEntry = null;
        if ($traitFormat === "categorical_free") {
            $traitCategoricalValue = $this->get_or_insert_trait_categorical_value($value, $valueOntology, $traitType);
            $traitEntry = new TraitCategoricalEntry();
            $traitEntry->setTraitCategoricalValue($traitCategoricalValue);
        } else {
            $traitEntry = new TraitNumericalEntry();
            $traitEntry->setValue($value);
        }
        $traitCitation = $this->get_or_insert_trait_citation($citation);
        $traitEntry->setTraitType($this->em->getReference('AppBundle:TraitType', $traitType));
        $traitEntry->setTraitCitation($traitCitation);
        $traitEntry->setOriginUrl($originURL);
        $traitEntry->setFennec($this->em->getReference('AppBundle:Organism', $fennec_id));
        $traitEntry->setWebuser($this->em->getReference('AppBundle:Webuser', $userID));
        $traitEntry->setPrivate(!$public);
        $this->em->persist($traitEntry);
        ++$this->insertedEntries;
    }
}