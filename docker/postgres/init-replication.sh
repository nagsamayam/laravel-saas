#!/bin/bash
set -e

# Dynamically append replication client rules to the active host configuration file
echo "host replication all all trust" >> "$PGDATA/pg_hba.conf"

# Forces PostgreSQL to seamlessly reload configuration files without requiring a reboot
pg_ctl reload
