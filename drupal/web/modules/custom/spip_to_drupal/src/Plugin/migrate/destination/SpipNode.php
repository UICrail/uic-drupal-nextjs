<?php

namespace Drupal\spip_to_drupal\Plugin\migrate\destination;

use Drupal\migrate\Plugin\migrate\destination\EntityContentBase;
use Drupal\migrate\Row;

/**
 * SPIP Node destination plugin that handles updates based on field_spip_id.
 *
 * @MigrateDestination(
 *   id = "spip_node_update"
 * )
 */
class SpipNodeUpdate extends EntityContentBase {

  /**
   * {@inheritdoc}
   */
  protected static function getEntityTypeId($plugin_id) {
    return 'node';
  }

  /**
   * {@inheritdoc}
   */
  public function import(Row $row, array $old_destination_id_values = []) {
    // Get the SPIP ID from the row
    $spip_id = $row->getDestinationProperty('field_spip_id');
    
    if ($spip_id) {
      // Try to find existing node with this SPIP ID
      $query = \Drupal::entityQuery('node')
        ->condition('type', 'article')
        ->condition('field_spip_id', $spip_id)
        ->range(0, 1)
        ->accessCheck(FALSE);
      
      $nids = $query->execute();
      
      if (!empty($nids)) {
        // Node exists, set the NID to update it
        $nid = reset($nids);
        $row->setDestinationProperty('nid', $nid);
        
        $title = $row->getDestinationProperty('title') ?? '';
        \Drupal::logger('spip_to_drupal')->info('Updating node @nid ("@title") for SPIP ID @spip_id', [
          '@nid' => $nid,
          '@title' => is_string($title) ? mb_substr($title, 0, 120) : '',
          '@spip_id' => $spip_id
        ]);
        
        // Return the existing destination IDs for update
        return [$nid];
      } else {
        $title = $row->getDestinationProperty('title') ?? '';
        \Drupal::logger('spip_to_drupal')->info('Creating node ("@title") for SPIP ID @spip_id', [
          '@spip_id' => $spip_id,
          '@title' => is_string($title) ? mb_substr($title, 0, 120) : ''
        ]);
      }
    }

    // Call parent import method for creation or update
    $result = parent::import($row, $old_destination_id_values);
    
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return [
      'nid' => [
        'type' => 'integer',
      ],
    ];
  }

}
