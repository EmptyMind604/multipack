<?php
namespace Ezp;

use \Cloudstek\PhpLaff\Packer;

if (class_exists( 'Ezp\MultiPack')) {
    return;
}

class MultiPack
{
    private $maxBoxes = 0;
    private $items = null;
    private $containers = null;
    private $containerIndex = null;
    private $best = null;

    public function __construct($items = null, $containers = null, $maxBoxes = 4) {
        if (is_array($items)) {
            $this->items = $items;
        }
        if (is_array($containers)) {
            $this->containers = $containers;
        }
        if (is_numeric($maxBoxes)) {
            $this->maxBoxes = $maxBoxes;
        }
    }

    function getBest(){
        return $this->best;
    }

    function setItems($items){
        $this->items = $items;
    }

    function getItems(){
        return $this->items;
    }

    function setContainers($containers) {
        $this->containers = $containers;
    }

    function getContainers(){
        return $this->containers;
    }

    function getContainerKeys(){
        $container_keys = [];
        foreach ( array_keys( $this->containers ) as $key ) {
            $container_keys[] = $key;
        }
        return $container_keys;
    }

    function getMaxBoxes(){
        return $this->maxBoxes;
    }

    function setMaxBoxes($maxBoxes){
        $this->maxBoxes = $maxBoxes;
    }

    function loadTestContainers() {
        return [
            //'box0' => null,
            'box1' => [
                'length' => 20,
                'width' => 20,
                'height' => 20,
            ],
            'box2' => [
                'length' => 40,
                'width' => 40,
                'height' => 40,
            ],
            'box3' => [
                'length' => 60,
                'width' => 60,
                'height' => 60,
            ],
            'box4' => [
                'length' => 80,
                'width' => 80,
                'height' => 80,
            ],
            'box5' => [
                'length' => 100,
                'width' => 100,
                'height' => 100,
            ],
        ];
    }

    function loadTestItems() {
        return [
            /*'item0' => [
                'length' => 1,
                'width' => 1,
                'height' => 100
            ],*/
            'item1' => [
                'length' => 50,
                'width' => 50,
                'height' => 8
            ],
            'item2' => [
                'length' => 33,
                'width' => 8,
                'height' => 8
            ],
            'item3' => [
                'length' => 16,
                'width' => 20,
                'height' => 8
            ],
            'item4' => [
                'length' => 3,
                'width' => 18,
                'height' => 8
            ],
            'item5' => [
                'length' => 14,
                'width' => 2,
                'height' => 8
            ],
        ];
    }

    function verifySolution( $container, $packed ) {
        $verify = true;
        $height = 0;
        foreach ( $packed as $layer ) {
            $layerHeight = 0;
            $area        = 0;
            foreach ( $layer as $item ) {
                $area += $item[ 'length' ] * $item[ 'width' ];
                if ( $item[ 'height' ] > $layerHeight ) {
                    $layerHeight = $item[ 'height' ];
                }
            }
            $height += $layerHeight;
            if ( $area > ( $container[ 'length' ] * $container[ 'width' ] ) ) {
                $verify = false;
            }
        }
        if ( $height > $container[ 'height' ] ) {
            $verify = false;
        }
        return $verify;
    }

    function checkSolution( $best, $check ) {
        $wasted_volume = 0;
        $num_boxes     = count( $check );
        foreach ( $check as $c ) {
            $wasted_volume += $c->get_remaining_volume();
        }
        if ( $num_boxes < $best[ 'count' ] ) {
            $best[ 'count' ]  = $num_boxes;
            $best[ 'wasted' ] = $wasted_volume;
            $best[ 'set' ]    = $check;
        } elseif ( ( $num_boxes == $best[ 'count' ] ) && ( $wasted_volume < $best[ 'wasted' ] ) ) {
            $best[ 'count' ]  = $num_boxes;
            $best[ 'wasted' ] = $wasted_volume;
            $best[ 'set' ]    = $check;
        }
        return $best;
    }

    function findBest( $sets ) {
        $checkArray = [];
        foreach ( $sets as $key => $set ) {
            $numContainers = count( $set );
            $wastedVolume  = 0;
            foreach ( $set as $c ) {
                if (!defined('USE_MAXHEIGHT')) {
                    define('USE_MAXHEIGHT',1);
                }
                $wastedVolume += $c->get_free_volume( USE_MAXHEIGHT );
            }
            if ( !isset( $checkArray[ $numContainers ] ) ) {
                $checkArray[ $numContainers ] = [
                    'wasted' => $wastedVolume,
                    'set' => $key
                ];
            } else {
                if ( $wastedVolume < $checkArray[ $numContainers ][ 'wasted' ] ) {
                    $checkArray[ $numContainers ] = [
                        'wasted' => $wastedVolume,
                        'set' => $key
                    ];
                }
            }
        }
        $best = $sets[ $checkArray[ min( array_keys( $checkArray ) ) ][ 'set' ] ];
        $this->best = $best;
        return $best;
    }

    function findOptimalContainers($items = false, $containers = false) {
        if ($containers === false) {
            $containers = $this->getContainers();
        } else {
            $this->setContainers($containers);
        }
        // make numeric index for sake of ease
        $container_keys = $this->getContainerKeys();
        if ($items === false) {
            $items = $this->getItems();
        } else {
            $this->setItems($items);
        }
        $num_containers = count( $containers );
        $try_containers = [ 0 ];

        //limit boxes for times sake
        // TODO: make a setting
        $max_boxes = $this->getMaxBoxes();
        $sets      = [];
        while ( ( count( $try_containers ) < $max_boxes ) || ( count( $try_containers ) < count( $items ) ) ) {
            // test container list
            $packed_containers = [];
            $packed            = null;
            $t_items           = $items;
            foreach ( $try_containers as $container_id ) {
                $packed              = $this->packContainer( $t_items, $containers[ $container_keys[ $container_id ] ] );
                $packed->set_container_id($container_keys[$container_id]);
                $packed_containers[] = $packed;
                if ( count( $packed->get_overflow() ) !== 0 ) {
                    foreach ( $packed->get_packed_boxes() as $layer ) {
                        foreach ( array_keys( $layer ) as $key ) {
                            unset( $t_items[ $key ] );
                        }
                    }
                } else {
                    break;
                }
            }
            if ( count( $packed->get_overflow() ) == 0 ) {
                $sets[ implode( ':', $try_containers ) ] = $packed_containers;
            }
            // increment try_containers array
            for ( $x = 0; $x < count( $try_containers ); $x++ ) {
                if ( $try_containers[ $x ] < $num_containers - 1 ) {
                    $try_containers[ $x ]++;
                    break;
                } else {
                    // if we fit in the current amount of boxes there is no point in adding another box.
                    if ( count( $packed->get_overflow() ) == 0 ) {
                        break 2;
                    }
                    $try_containers[ $x ] = 0;
                    if ( !isset( $try_containers[ $x + 1 ] ) ) {
                        $try_containers[ $x + 1 ] = 0;
                        break;
                    }
                }
            }
        }
        return $this->findBest( $sets );
    }

    function packContainer( $items, $container ) {
        $laff = new Packer();
        try {
            $laff->pack( $items, $container );
            $containerDim = $laff->get_container_dimensions();
            if ( ( isset( $container[ 'height' ] ) ) && ( $containerDim[ 'height' ] > $container[ 'height' ] ) ) {
                throw( new OutOfRangeException( 'Sanity Failure - Packed Too High' ) );
            }
            if ( !$this->verifySolution( $laff->get_container_dimensions(), $laff->get_packed_boxes() ) ) {
                throw( new OutOfRangeException( 'Sanity Failure - Verification Failed' ) );
            }
        } catch ( OutOfRangeException $e ) {
            echo $e->getMessage() . PHP_EOL;
            return false;
        }
        return $laff;
    }
}

