<?php
/**
 * Copyright (c) 2013 Bart Visscher <bartv@thisnet.nl>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */


namespace OC\DB;

class AdapterSqlite extends Adapter {
	public function fixupStatement($statement) {
		$statement = preg_replace('/`(\w+)` ILIKE \?/', 'LOWER($1) LIKE LOWER(?)', $statement);
		$statement = str_replace( '`', '"', $statement );
		$statement = str_ireplace( 'NOW()', 'datetime(\'now\')', $statement );
		$statement = str_ireplace( 'UNIX_TIMESTAMP()', 'strftime(\'%s\',\'now\')', $statement );
		return $statement;
	}

	public function insertIfNotExist($table, $input) {
		// NOTE: For SQLite we have to use this clumsy approach
		// otherwise all fieldnames used must have a unique key.
		$query = 'SELECT COUNT(*) FROM `' . $table . '` WHERE ';
		$inserts = array();
		foreach ($input as $key => $value) {
			$query .= '`' . $key . '`';
			if (is_null($value)) {
				$query .= ' IS NULL AND ';
			} else {
				$inserts[] = $value;
				$query .= ' = ? AND ';
			}
		}
		$query = substr($query, 0, strlen($query) - 5);

		try {
			$stmt = $this->conn->prepare($query);
			$result = $stmt->execute($inserts);
		} catch(\Doctrine\DBAL\DBALException $e) {
			$entry = 'DB Error: "'.$e->getMessage() . '"<br />';
			$entry .= 'Offending command was: ' . $query . '<br />';
			\OC_Log::write('core', $entry, \OC_Log::FATAL);
			$l = \OC::$server->getL10N('lib');
			throw new \OC\HintException(
				$l->t('Database Error'),
				$l->t('Please contact your system administrator.'),
				0,
				$e
			);
		}

		if ($stmt->fetchColumn() === '0') {
			$query = 'INSERT INTO `' . $table . '` (`'
				. implode('`,`', array_keys($input)) . '`) VALUES('
				. str_repeat('?,', count($input)-1).'? ' . ')';
		} else {
			return 0; //no rows updated
		}

		try {
			$statement = $this->conn->prepare($query);
			$result = $statement->execute(array_values($input));
		} catch(\Doctrine\DBAL\DBALException $e) {
			$entry = 'DB Error: "'.$e->getMessage() . '"<br />';
			$entry .= 'Offending command was: ' . $query.'<br />';
			\OC_Log::write('core', $entry, \OC_Log::FATAL);
			$l = \OC::$server->getL10N('lib');
			throw new \OC\HintException(
				$l->t('Database Error'),
				$l->t('Please contact your system administrator.'),
				0,
				$e
			);
		}

		return $result;
	}
}
