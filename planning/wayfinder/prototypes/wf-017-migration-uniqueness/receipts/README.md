# Receipt contract

Each JSON receipt records the framework and database versions, selected migration API, exact identity and
unique-index shape, the losing claimant's SQLSTATE, discovered unique and foreign-key constraints, final row
counts, and a pass verdict. The ten committed receipts are disposable-run evidence, not a compatibility
promise for unpinned future framework or database versions.
