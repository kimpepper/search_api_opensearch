<?php

namespace Drupal\opensearch\SearchAPI;

use Drupal\search_api\IndexInterface;

interface BackendClientInterface {

  /**
   * Indexes the specified items.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The search index for which items should be indexed.
   * @param \Drupal\search_api\Item\ItemInterface[] $items
   *   An array of items to be indexed, keyed by their item IDs.
   *
   * @return string[]
   *   The IDs of all items that were successfully indexed.
   *
   * @throws \Drupal\search_api\SearchApiException
   *   Thrown if indexing was prevented by a fundamental configuration error.
   */
  public function indexItems(IndexInterface $index, array $items): array;

  /**
   * Deletes the specified items from the index.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The index from which items should be deleted.
   * @param string[] $item_ids
   *   The IDs of the deleted items.
   *
   * @throws \Drupal\search_api\SearchApiException
   *   Thrown if an error occurred while trying to delete the items.
   */
  public function deleteItems(IndexInterface $index, array $item_ids): void;

  /**
   * Adds a new index to this server.
   *
   * If the index was already added to the server, the object should treat this
   * as if removeIndex() and then addIndex() were called.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The index to add.
   *
   * @throws \Drupal\search_api\SearchApiException
   *   Thrown if an error occurred while adding the index.
   */
  public function addIndex(\Drupal\search_api\IndexInterface $index): void;

  /**
   * Notifies the server that an index attached to it has been changed.
   *
   * If any user action is necessary as a result of this, the method should
   * set a message to notify the user.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The updated index.
   *
   * @throws \Drupal\search_api\SearchApiException
   *   Thrown if an error occurred while reacting to the change.
   */
  public function updateIndex(IndexInterface $index): void;

  /**
   * Removes an index from this server.
   *
   * This might mean that the index has been deleted, or reassigned to a
   * different server. If you need to distinguish between these cases, inspect
   * $index->getServerId().
   *
   * If the index wasn't added to the server previously, the method call should
   * be ignored.
   *
   * Implementations of this method should also check whether
   * $index->isReadOnly() and don't delete any indexed data if it is.
   *
   * @param \Drupal\search_api\IndexInterface|string $index
   *   Either an object representing the index to remove, or its ID (if the
   *   index was completely deleted).
   *
   * @throws \Drupal\search_api\SearchApiException
   *   Thrown if an error occurred while removing the index.
   */
  public function removeIndex($index): void;

  /**
   * Deletes all the items from the index.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The index for which items should be deleted.
   * @param string|null $datasource_id
   *   (optional) If given, only delete items from the datasource with the
   *   given ID.
   *
   * @throws \Drupal\search_api\SearchApiException
   *   Thrown if an error occurred while trying to delete indexed items.
   */
  public function clearIndex(IndexInterface $index, string $datasource_id = NULL): void;
}
