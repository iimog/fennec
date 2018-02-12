<?php

namespace AppBundle\API\Details;

use AppBundle\API\Details;
use Tests\AppBundle\API\WebserviceTestCase;

class OrganismsOfProjectTest extends WebserviceTestCase
{
    const NICKNAME = 'detailsOrganismsOfProjectTestUser';
    const USERID = 'detailsOrganismsOfProjectTestUser';
    const PROVIDER = 'detailsOrganismsOfProjectTestUser';

    private $emData;
    private $emUser;
    private $organismsOfProject;

    public function setUp()
    {
        $kernel = self::bootKernel();

        $this->emData = $kernel->getContainer()
            ->get('doctrine')
            ->getManager('test_data');
        $this->emUser = $kernel->getContainer()
            ->get('doctrine')
            ->getManager('test_user');
        $this->organismsOfProject = $kernel->getContainer()->get(Details\OrganismsOfProject::class);
    }

    public function tearDown()
    {
        parent::tearDown();

        $this->emData->close();
        $this->emData = null; // avoid memory leaks
        $this->emUser->close();
        $this->emUser = null; // avoid memory leaks
    }

    public function testNotLoggedIn()
    {
        $dbversion = $this->default_db;
        $projectId = 1;
        $dimension = "row";
        $user = null;
        $results = $this->organismsOfProject->execute($projectId, $dimension, $user, $dbversion);
        $expected = array("error" => Details\OrganismsOfProject::ERROR_NOT_LOGGED_IN);
        $this->assertEquals($expected, $results, 'User is not loggend in, return error message');
    }


    public function testOrganismsOfProjectWithRows()
    {
        $dbversion = $this->default_db;
        $dimension = "rows";
        $user = $this->em->getRepository('AppBundle:FennecUser')->findOneBy(array(
            'username' => OrganismsOfProjectTest::NICKNAME
        ));
        $projectId = $this->em->getRepository('AppBundle:WebuserData')->findOneBy(array(
            'webuser' => $user
        ))->getWebuserDataId();

        $results = $this->organismsOfProject->execute($projectId, $dimension, $user, $dbversion);
        $expected = array(
            3, 42
        );
        $this->assertEquals(2, count($results), 'Example project, return uniq organism ids for rows');
        $this->assertContains($expected[0], $results, 'Example project, return uniq organism ids for rows');
        $this->assertContains($expected[1], $results, 'Example project, return uniq organism ids for rows');
    }


    public function testOrganismsOfProjectWithColumns()
    {
        $dbversion = $this->default_db;
        $dimension = "columns";
        $user = $this->em->getRepository('AppBundle:FennecUser')->findOneBy(array(
            'username' => OrganismsOfProjectTest::NICKNAME
        ));
        $projectId = $this->em->getRepository('AppBundle:WebuserData')->findOneBy(array(
            'webuser' => $user
        ))->getWebuserDataId();
        $results = $this->organismsOfProject->execute($projectId, $dimension, $user, $dbversion);
        $expected = array(
            1340, 1630
        );
        $this->assertEquals(2, count($results), 'Example project, return uniq organism ids for samples');
        $this->assertContains($expected[0], $results, 'Example project, return uniq organism ids for samples');
        $this->assertContains($expected[1], $results, 'Example project, return uniq organism ids for samples');
    }

    public function testNoValidUserOfProject(){
        $dbversion = $this->default_db;
        $dimension = "rows";
        $user = $this->em->getRepository('AppBundle:FennecUser')->findOneBy(array(
            'username' => OrganismsOfProjectTest::NICKNAME
        ));
        $noValidProjectId = 15;
        $results = $this->organismsOfProject->execute($noValidProjectId, $dimension, $user, $dbversion);
        $expected = array("error" => OrganismsOfProject::ERROR_PROJECT_NOT_FOUND);
        $this->assertEquals($expected, $results, 'Project does not belong to user, return error message');

    }
}