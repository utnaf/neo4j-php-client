<?php

declare(strict_types=1);

/*
 * This file is part of the Laudis Neo4j package.
 *
 * (c) Laudis technologies <http://laudis.tech>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Laudis\Neo4j\Tests\Integration;

use Ds\Vector;
use Laudis\Neo4j\ClientBuilder;
use Laudis\Neo4j\Contracts\ClientInterface;
use Laudis\Neo4j\Databags\Statement;
use Laudis\Neo4j\Network\Bolt\BoltConfig;
use Laudis\Neo4j\Tests\Base\ClientTest;

final class ClientIntegrationTest extends ClientTest
{
    public function createClient(): ClientInterface
    {
        $builder = ClientBuilder::create();
        $aliases = new Vector($this->connectionAliases());
        $aliases = $aliases->slice(0, $aliases->count() - 1);
        foreach ($aliases as $index => $alias) {
            $alias = (new Vector($alias))->first();
            if ($index % 2 === 0) {
                $explosion = explode('-', $alias);
                $version = $explosion[count($explosion) - 1];
                $builder = $builder->addBoltConnection('bolt-'.$version, 'bolt://neo4j:test@neo4j-'.$version);
                $builder = $builder->addBoltConnection('http-'.$version, 'http://neo4j:test@neo4j-'.$version);
            }
        }

        $builder = $builder->addBoltConnection('cluster', 'bolt://neo4j:test@core1', BoltConfig::create()->withAutoRouting(true));

        return $builder->build();
    }

    public function connectionAliases(): iterable
    {
        $tbr = [];
        foreach (explode(',', (string) getenv('NEO4J_VERSIONS_AVAILABLE')) as $version) {
            $tbr[] = ['bolt-'.$version];
            $tbr[] = ['http-'.$version];
        }

        $tbr[] = ['cluster'];

        return $tbr;
    }

    public function testEqualEffect(): void
    {
        $statement = new Statement(
            'merge(u:User{email: $email}) on create set u.uuid=$uuid return u',
            ['email' => 'a@b.c', 'uuid' => 'cc60fd69-a92b-47f3-9674-2f27f3437d66']
        );

        foreach (explode(',', (string) getenv('NEO4J_VERSIONS_AVAILABLE')) as $version) {
            $x = $this->client->runStatement($statement, 'bolt-'.$version);
            $y = $this->client->runStatement($statement, 'http-'.$version);

            self::assertEquals($x, $y);
            self::assertEquals($x->toArray(), $y->toArray());
        }
    }
}
