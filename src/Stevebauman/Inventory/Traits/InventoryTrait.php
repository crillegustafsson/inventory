<?php

namespace Stevebauman\Inventory\Traits;

use Stevebauman\Inventory\Exceptions\StockNotFoundException;
use Stevebauman\Inventory\Exceptions\StockAlreadyExistsException;
use Illuminate\Database\Eloquent\SoftDeletingTrait;

/**
 * Class InventoryTrait
 * @package Stevebauman\Inventory\Traits
 */
trait InventoryTrait {

    /**
     * Soft deleting for inventory item recovery
     */
    use SoftDeletingTrait;

    /**
     * Location helper functions
     */
    use LocationTrait;

    /**
     * Set's the models constructor method to automatically assign the
     * user_id's attribute to the current logged in user
     */
    use UserIdentificationTrait;

    /**
     * Helpers for starting database transactions
     */
    use DatabaseTransactionTrait;

    /**
     * Returns the total sum of the current stock
     *
     * @return mixed
     */
    public function getTotalStock()
    {
        return $this->stocks->sum('quantity');
    }

    /**
     * Returns true/false if the inventory has a metric present
     *
     * @return bool
     */
    public function hasMetric()
    {
        return ($this->metric ? true : false);
    }

    /**
     * Returns the inventory's metric symbol
     *
     * @return mixed
     */
    public function getMetricSymbol()
    {
        return $this->metric->symbol;
    }

    /**
     * Returns true/false if the inventory has stock
     *
     * @return bool
     */
    public function isInStock()
    {
        return ($this->getTotalStock() > 0 ? true : false);
    }

    /**
     * Creates a stock record to the current inventory item
     *
     * @param $quantity
     * @param $location
     * @param string $reason
     * @param int $cost
     * @param null $aisle
     * @param null $row
     * @param null $bin
     * @return mixed
     * @throws StockAlreadyExistsException
     * @throws StockNotFoundException
     * @throws \Stevebauman\Inventory\Traits\InvalidLocationException
     * @throws \Stevebauman\Inventory\Traits\NoUserLoggedInException
     */
    public function createStockOnLocation($quantity, $location, $reason = '', $cost = 0, $aisle = NULL, $row = NULL, $bin = NULL)
    {
        $location = $this->getLocation($location);

        try {

            if ($this->getStockFromLocation($location)) {

                $message = sprintf('Stock already exists on location %s', $location->name);

                throw new StockAlreadyExistsException($message);

            }

        } catch (StockNotFoundException $e) {

            $insert = array(
                'inventory_id' => $this->id,
                'location_id' => $location->id,
                'quantity' => 0,
                'aisle' => $aisle,
                'row' => $row,
                'bin' => $bin,
            );

            $stock = $this->stocks()->create($insert);

            return $stock->put($quantity, $reason, $cost);

        }
    }

    /**
     * Takes the specified amount ($quantity) of stock from specified stock location
     *
     * @param string|int $quantity
     * @param $location
     * @param string $reason
     * @return array
     * @throws StockNotFoundException
     */
    public function takeFromLocation($quantity, $location, $reason = '')
    {
        if (is_array($location)) {

            return $this->takeFromManyLocations($quantity, $location, $reason);

        } else {

            $stock = $this->getStockFromLocation($location);

            if ($stock->take($quantity, $reason)) {

                return $this;

            }

        }
    }

    /**
     * Takes the specified amount ($quantity) of stock from the specified stock locations
     *
     * @param string|int $quantity
     * @param array $locations
     * @param string $reason
     * @return array
     * @throws StockNotFoundException
     */
    public function takeFromManyLocations($quantity, $locations = array(), $reason = '')
    {
        $stocks = array();

        foreach ($locations as $location) {

            $stock = $this->getStockFromLocation($location);

            $stocks[] = $stock->take($quantity, $reason);

        }

        return $stocks;
    }

    /**
     * Alias for the `take` function
     *
     * @param $quantity
     * @param $location
     * @param string $reason
     * @return array
     */
    public function removeFromLocation($quantity, $location, $reason = '')
    {
        return $this->takeFromLocation($quantity, $location, $reason);
    }

    /**
     * Alias for the `takeFromMany` function
     *
     * @param $quantity
     * @param array $locations
     * @param string $reason
     * @return array
     */
    public function removeFromManyLocations($quantity, $locations = array(), $reason = '')
    {
        return $this->takeFromManyLocations($quantity, $locations, $reason);
    }

    /**
     * Puts the specified amount ($quantity) of stock into the specified stock location(s)
     *
     * @param string|int $quantity
     * @param $location
     * @param string $reason
     * @param int $cost
     * @return array
     * @throws StockNotFoundException
     */
    public function putToLocation($quantity, $location, $reason = '', $cost = 0)
    {
        if (is_array($location)) {

            return $this->putToManyLocations($quantity, $location);

        } else {

            $stock = $this->getStockFromLocation($location);

            if ($stock->put($quantity, $reason, $cost)) {

                return $this;

            }

        }
    }

    /**
     * Puts the specified amount ($quantity) of stock into the specified stock locations
     *
     * @param $quantity
     * @param array $locations
     * @param string $reason
     * @param int $cost
     * @return array
     * @throws StockNotFoundException
     */
    public function putToManyLocations($quantity, $locations = array(), $reason = '', $cost = 0)
    {
        $stocks = array();

        foreach ($locations as $location) {

            $stock = $this->getStockFromLocation($location);

            $stocks[] = $stock->put($quantity, $reason, $cost);

        }

        return $stocks;
    }

    /**
     * Alias for the `put` function
     *
     * @param $quantity
     * @param $location
     * @param string $reason
     * @param int $cost
     * @return array
     */
    public function addToLocation($quantity, $location, $reason = '', $cost = 0)
    {
        return $this->putToLocation($quantity, $location, $reason, $cost);
    }

    /**
     * Alias for the `putToMany` function
     *
     * @param $quantity
     * @param array $locations
     * @param string $reason
     * @param int $cost
     * @return array
     */
    public function addToManyLocations($quantity, $locations = array(), $reason = '', $cost = 0)
    {
        return $this->putToManyLocations($quantity, $locations, $reason, $cost);
    }

    /**
     * Moves a stock from one location to another
     *
     * @param $fromLocation
     * @param $toLocation
     * @return mixed
     * @throws StockNotFoundException
     */
    public function moveStock($fromLocation, $toLocation)
    {
        $stock = $this->getStockFromLocation($fromLocation);

        $toLocation = $this->getLocation($toLocation);

        return $stock->moveTo($toLocation);
    }

    /**
     * Retrieves an inventory stock from a given location
     *
     * @param $location
     * @return mixed
     * @throws InvalidLocationException
     * @throws StockNotFoundException
     */
    public function getStockFromLocation($location)
    {
        $location = $this->getLocation($location);

        $stock = $this->stocks()
            ->where('inventory_id', $this->id)
            ->where('location_id', $location->id)
            ->first();

        if ($stock) {

            return $stock;

        } else {

            $message = sprintf('No stock was found from location %s', $location->name);

            throw new StockNotFoundException($message);

        }
    }

}