DocumentArchive - Legacy Archive Reference Sequence Fix V81.2
================================================================

Problem
-------
Some imported books were assigned reference_sequence = 0 because the importer
subtracted the current reference_start_number setting from the old book number.
When that setting contained a later number such as 251230388, multiple imported
books could receive sequence 0 and violate this unique key:

documents_reference_year_reference_sequence_unique

Correct rule
------------
The project numbering base is fixed at 251230000 for every Gregorian year.
For example:

251230388 -> reference_sequence 388

Files
-----
scripts/apply_legacy_archive_reference_sequence_fix_v81_2.php
scripts/check_legacy_archive_reference_sequence_fix_v81_2.php
scripts/repair_legacy_archive_reference_sequences_v81_2.php

Installation
------------
php scripts/apply_legacy_archive_reference_sequence_fix_v81_2.php
php scripts/check_legacy_archive_reference_sequence_fix_v81_2.php

Preview database repair
-----------------------
php scripts/repair_legacy_archive_reference_sequences_v81_2.php

Execute database repair
-----------------------
php scripts/repair_legacy_archive_reference_sequences_v81_2.php --execute --confirm=FIX-LEGACY-SEQUENCES

Then clear caches and rerun the same CSV import. Previously successful records
will be skipped by legacy_record_id/reference number. Rows that failed before
creating a document will be attempted again.

The repair does not delete documents or attachment files.
