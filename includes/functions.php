<?php
error_reporting(E_ALL);
ini_set("display_errors","On");
/**
 * Centralized function to log events to the database.
 *
 * This function is designed to be included and used wherever an action needs to be logged.
 * It relies on the global $pdo object from config.php and the $USER['username'].
 *
 * @param string $action The description of the action to be logged.
 */
function log_event($action) {

    // Access the global PDO object and session username
    global $pdo, $USER;
    
    $username = $USER['username'] ?? 'system';

    // Ensure PDO object is available
    if (!isset($pdo)) {
        error_log("log_event failed: PDO object is not available.");
        return;
    }

    try {
        $sql = "INSERT INTO logs (username, action) VALUES (:username, :action)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'username' => $username,
            'action' => $action
        ]);
    } catch (PDOException $e) {
        // If logging fails, we don't want to break the user's current action.
        // Instead, we log the error to the server's error log for the administrator to review.
        error_log("Failed to log event '{$action}' for user '{$username}': " . $e->getMessage());
    }
}

/**
 * Recursively builds a hierarchical tree from a flat array of elements.
 * Includes cycle detection to prevent infinite loops.
 *
 * @param array $elements Flat array of associative arrays, each must contain 'id' and 'parent_id'.
 * @param mixed|null $parentId The ID of the parent element to start the branch from.
 * @param array $path An array to track the current path to detect cycles.
 * @return array Returns an array of tree nodes.
 */
function build_tree(array $elements, $parentId = null, array $path = []) {
    $branch = [];
    if ($parentId !== null) {
        if (in_array($parentId, $path)) {
            // Cycle detected, log error and stop this branch.
            error_log("Cycle detected in department hierarchy at ID: " . $parentId);
            return [];
        }
        $path[] = $parentId;
    }

    foreach ($elements as $element) {
        if ($element['parent_id'] == $parentId) {
            $children = build_tree($elements, $element['id'], $path);
            if ($children) {
                $element['children'] = $children;
            }
            $branch[] = $element;
        }
    }
    return $branch;
}

