<?php

namespace Silktide\Reposition\Sql\Test\Normaliser;

use Silktide\Reposition\Exception\MetadataException;
use Silktide\Reposition\Metadata\EntityMetadata;
use Silktide\Reposition\Sql\Normaliser\SqlNormaliser;

class SqlNormaliserTest extends \PHPUnit_Framework_TestCase {

    protected $metadataMocks = [];

    /**
     * @dataProvider dataSetProvider
     *
     * @param array $dataSet
     * @param array $expectedData
     * @param array $entityMap
     * @param string $entity
     */
    public function testDenormalisation(array $dataSet, array $expectedData, array $entityMap = [], $thisEntity = "")
    {
        $propField = EntityMetadata::METADATA_RELATIONSHIP_PROPERTY;

        $relationships = [
            "one" => [
                "two" => [
                    $propField => "twos"
                ],
                "three" => [
                    $propField => "threes"
                ]
            ],
            "two" => [],
            "three" => [
                "four" => [
                    $propField => "fours"
                ],
                "two" => [
                    $propField => "twos"
                ]
            ],
            "four" => []
        ];

        $this->metadataMocks = [];
        foreach ($relationships as $entity => $children) {
            $metadataMock = \Mockery::mock("Silktide\\Reposition\\Metadata\\EntityMetadata");
            $metadataMock->shouldReceive("getCollection")->andReturn($entity);
            $metadataMock->shouldReceive("getRelationships")->andReturn($children);
            $this->metadataMocks[$entity] = $metadataMock;
        }

        $metadataProvider = \Mockery::mock("Silktide\\Reposition\\Metadata\\EntityMetadataProviderInterface");
        $metadataProvider->shouldReceive("getEntityMetadata")->andReturnUsing([$this, "getMetadataMock"]);

        $normaliser = new SqlNormaliser();
        $options = [
            "metadataProvider" => $metadataProvider,
            "entityMap" => $entityMap,
            "entity" => $thisEntity
        ];

        $result = $normaliser->denormalise($dataSet, $options);

        $this->assertEquals($expectedData, $result);

    }

    public function getMetadataMock($entity)
    {
        if (!isset($this->metadataMocks[$entity])) {
            throw new MetadataException("TEST: could not find metadata for '$entity'");
        }
        return $this->metadataMocks[$entity];
    }

    public function dataSetProvider()
    {
        return [
            [ // #0 simplest mapping possible
                [["field1" => "value1", "field2" => "value2", "field3" => "value3"]],
                [["field1" => "value1", "field2" => "value2", "field3" => "value3"]]
            ],
            [ // #1 simple mapping, removing prefix
                [["a__field1" => "value1", "a__field2" => "value2", "a__field3" => "value3"]],
                [["field1" => "value1", "field2" => "value2", "field3" => "value3"]]
            ],
            [ // #2 single record, single child
                [["one__field1" => "value1", "one__field2" => "value2", "two__field3" => "value3", "two__field4" => "value4"]],
                [["field1" => "value1", "field2" => "value2", "twos" => [["field3" => "value3", "field4" => "value4"]]]],
                ["two" => "two"],
                "one"
            ],
            [ // #3 single record, multiple children
                [
                    ["one__field1" => "value1", "one__field2" => "value2", "two__field3" => "value3", "two__field4" => "value4"],
                    ["one__field1" => "value1", "one__field2" => "value2", "two__field3" => "value5", "two__field4" => "value6"],
                    ["one__field1" => "value1", "one__field2" => "value2", "two__field3" => "value7", "two__field4" => "value8"]
                ],
                [["field1" => "value1", "field2" => "value2", "twos" => [
                    ["field3" => "value3", "field4" => "value4"],
                    ["field3" => "value5", "field4" => "value6"],
                    ["field3" => "value7", "field4" => "value8"]
                ]]],
                ["two" => "two"],
                "one"
            ],
            [ // #4 multiple records, multiple children
                [
                    ["one__field1" => "value1", "one__field2" => "value2", "two__field3" => "value3", "two__field4" => "value4"],
                    ["one__field1" => "value1", "one__field2" => "value2", "two__field3" => "value5", "two__field4" => "value6"],
                    ["one__field1" => "value7", "one__field2" => "value8", "two__field3" => "value9", "two__field4" => "value10"],
                    ["one__field1" => "value7", "one__field2" => "value8", "two__field3" => "value11", "two__field4" => "value12"]
                ],
                [
                    ["field1" => "value1", "field2" => "value2", "twos" => [
                        ["field3" => "value3", "field4" => "value4"],
                        ["field3" => "value5", "field4" => "value6"]
                    ]],
                    ["field1" => "value7", "field2" => "value8", "twos" => [
                        ["field3" => "value9", "field4" => "value10"],
                        ["field3" => "value11", "field4" => "value12"]
                    ]]
                ],
                ["two" => "two"],
                "one"
            ],
            [ // #5 ignoring children
                [
                    ["one__field1" => "value1", "one__field2" => "value2", "two__field3" => "value3", "two__field4" => "value4"]
                ],
                [
                    ["field1" => "value1", "field2" => "value2"]
                ],
                [],
                "one"
            ],
            [ // #6 single record, multiple relationships
                [
                    ["one__field1" => "value1", "one__field2" => "value2", "two__field3" => "value3", "two__field4" => "value4", "three__field5" => null, "three__field6" => null],
                    ["one__field1" => "value1", "one__field2" => "value2", "two__field3" => null, "two__field4" => null, "three__field5" => "value5", "three__field6" => "value6"]
                ],
                [["field1" => "value1", "field2" => "value2", "twos" => [
                        ["field3" => "value3", "field4" => "value4"]
                    ], "threes" => [
                        ["field5" => "value5", "field6" => "value6", "twos" => []]
                    ]
                ]],
                ["two" => "two", "three" => "three"],
                "one"
            ],
            [ // #7 multiple records, multiple relationships
                [
                    ["one__field1" => "value1", "one__field2" => "value2", "two__field3" => "value3", "two__field4" => "value4", "three__field5" => null, "three__field6" => null],
                    ["one__field1" => "value1", "one__field2" => "value2", "two__field3" => null, "two__field4" => null, "three__field5" => "value5", "three__field6" => "value6"],
                    ["one__field1" => "value7", "one__field2" => "value8", "two__field3" => null, "two__field4" => null, "three__field5" => "value9", "three__field6" => "value10"],
                    ["one__field1" => "value7", "one__field2" => "value8", "two__field3" => null, "two__field4" => null, "three__field5" => "value11", "three__field6" => "value12"],
                    ["one__field1" => "value7", "one__field2" => "value8", "two__field3" => null, "two__field4" => null, "three__field5" => null, "three__field6" => null]
                ],
                [
                    ["field1" => "value1", "field2" => "value2", "twos" => [
                            ["field3" => "value3", "field4" => "value4"]
                        ], "threes" => [
                            ["field5" => "value5", "field6" => "value6", "twos" => []]
                        ]
                    ],
                    ["field1" => "value7", "field2" => "value8", "twos" => [], "threes" => [
                        ["field5" => "value9", "field6" => "value10", "twos" => []],
                        ["field5" => "value11", "field6" => "value12", "twos" => []]
                    ]
                    ]
                ],
                ["two" => "two", "three" => "three"],
                "one"
            ],
            [ // #8 single record, multi-level relationships
                [
                    ["one__field1" => "value1", "one__field2" => "value2", "four__field3" => "value3", "four__field4" => "value4", "three__field5" => "value5", "three__field6" => "value6"]
                ],
                [
                    ["field1" => "value1", "field2" => "value2", "threes" => [
                            ["field5" => "value5", "field6" => "value6", "fours" => [
                                ["field3" => "value3", "field4" => "value4"]
                            ]]
                    ]]
                ],
                ["four" => "two", "three" => "three"],
                "one"
            ]
        ];
    }

}
 