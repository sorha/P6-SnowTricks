<?php

namespace Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class TrickControllerTest extends WebTestCase
{
    public function testdetails()
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/trick/details/commodi-rerum-earum');

        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        // Should show the trick main image
        $this->assertSame(1, $crawler->filter('div#trickMainImage')->count());
    }

    public function testcreate()
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/trick/create');

        $this->assertEquals(200, $client->getResponse()->getStatusCode());


        $form = $crawler->selectButton('Créer le nouveau trick')->form();
        $form['trick[name]'] = 'Nom du trick';
        $form['trick[description]'] = 'Description du nouveau trick';
        $form['trick[category]'] = '81';
        $form['trick[mainImageUrl]'] = 'https://coresites-cdn.factorymedia.com/whitelines_new/wp-content/uploads/2016/12/Whitelines-Best-Snowboard-Tricks-2016-featured.jpg';
        $crawler = $client->submit($form);

        //$crawler = $client->followRedirect();

        echo $client->getResponse()->getContent();
    }
}