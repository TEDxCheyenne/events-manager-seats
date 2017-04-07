<?php

/**
 * Class SimpleOrm
 * Not really an ORM, but is very useful for "unjoining" data.  So if you have rows of join data, use this to create structured arrays.
 */
class SimpleOrm {

	/**
	 * @param $collection   mixed
	 * @param $keepers  array   properties to keep in the collection
	 *
	 * @return array
	 */
	static function pluck( $collection, $keepers ) {
		$result     = [];
		$collection = (array) $collection;
		foreach ( $keepers as $keeper ) {
			if ( isset( $collection[ $keeper ] ) ) {
				$result[ $keeper ] = $collection[ $keeper ];
			}
		}

		return $result;
	}

	/**
     * Imposes ORM-style structure on unstructured rows of data.
     *
	 * @param $rows array   Unstructured rows of data
     *
     * array(
     *  array(
     *      'event_id' => 1,
     *      'event_name' => "Event One",
     *      'attendee_id' => 1
     *      'attendee_first_name' => "John",
     *      'attendee_last_name' => "Doe",
     *  ),
     *  array(
     *      'event_id' => 1,
     *      'event_name' => "Event One",
     *      'attendee_id' => 2
     *      'attendee_first_name' => "Jane",
     *      'attendee_last_name' => "Doe",
     *  ),
     *  array(
     *      'event_id' => 2,
     *      'event_name' => "Event Two",
     *      'attendee_id' => 1
     *      'attendee_first_name' => "John",
     *      'attendee_last_name' => "Doe",
     *  ),
     *  array(
     *      'event_id' => 1,
     *      'event_name' => "Event One",
     *      'attendee_id' => 1
     *      'attendee_first_name' => "John",
     *      'attendee_last_name' => "Doe",
     *  ),
     * )
     *
	 * @param $structure    array    Structure to impose upon the data.
     *
     * array(
     *  'Event' => array(
     *      '__key' => 'event_id',
     *      'event_id'
     *      'event_name',
     *      'Attendees' => array(
     *          '__key' => 'attendee_id',
     *          'attendee_id',
     *          'attendee_first_name',
     *          'attendee_last_name',
     *      )
     *  )
     * )
	 *
	 * @return array
     *
     *
	 */
	static function map( $rows, $structure ) {

		$rows = (array) $rows;
		$groupedByKey = [];
		foreach ( $structure as $entity => $properties ) {

			if ( ! isset( $properties['__key'] ) ) {
				return [];
			}
			$primary_key = $properties['__key'];

			unset( $properties['__key'] );

			$keepers  = [];
			$children = [];

			foreach ( $properties as $propertyIdx => $property ) {
				if( is_array($property) ) {
					$children[ $propertyIdx ] = $property;
				} else {
					$keepers[] = $property;
				}
			}

			foreach ( $rows as $row_id => $rowObj ) {
				$row        = (array) $rowObj;
				$currentKey = $row[ $primary_key ];

				if ( isset( $groupedByKey[ $entity ][ $currentKey ] ) ) {
					continue;
				}

				$preparedRow = self::pluck( $row, $keepers );

				$subRows = array_filter($rows, function($_row) use ($primary_key, $currentKey) {
					return $_row->{$primary_key} === $currentKey;
				});

				foreach ( $children as $childEntity => $childProperties ) {
					$childStructure = array(
						$childEntity => $childProperties,
					);
					$preparedRow += self::map( $subRows, $childStructure );
				}

				$groupedByKey[ $entity ][ $currentKey ] = $preparedRow ;
			}
		}

		return $groupedByKey;
	}
}