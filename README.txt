IT490 Deployment — Person 1 Packaging System
=============================================

DIRECTORY STRUCTURE
-------------------
it490-packaging/
├── build_bundle.sh
├── app-src/
│   ├── frontend/
│   │   └── index.html
│   └── backend/
│       └── api.php
├── builds/
│   ├── it490_v1.tgz
│   ├── it490_v2.tgz
│   └── it490_v3.tgz
└── manifests/
    ├── manifest_v1.json
    ├── manifest_v2.json
    └── manifest_v3.json


HOW TO BUILD A NEW VERSION
---------------------------
1. Make your changes to files inside app-src/frontend/ and app-src/backend/
2. Run the following command from inside the it490-packaging/ directory:

       ./build_bundle.sh <version_number>

   Example:
       ./build_bundle.sh 4

3. The script will produce:
   - builds/it490_v4.tgz
   - manifests/manifest_v4.json

4. If the Deploy VM directory /srv/deploy/bundles exists and is mounted,
   the script copies both files there automatically.
   If not, copy them manually:

       scp builds/it490_v4.tgz user@deploy-vm:/srv/deploy/bundles/
       scp manifests/manifest_v4.json user@deploy-vm:/srv/deploy/bundles/


MANIFEST SCHEMA (for Person 2 and Person 3)
--------------------------------------------
Every bundle contains a manifest.json at the root of the archive.
The structure is fixed and must not be changed without notifying the team.

{
    "version": "<version_number>",
    "bundle_name": "it490_v<version_number>.tgz",
    "targets": [
        {
            "role": "frontend",
            "files": [
                {
                    "source": "frontend/<filename>",
                    "destination": "/var/www/html/<filename>"
                }
            ],
            "service_restart": "apache2"
        },
        {
            "role": "backend",
            "files": [
                {
                    "source": "backend/<filename>",
                    "destination": "/var/www/html/<filename>"
                }
            ],
            "service_restart": "apache2"
        }
    ]
}

- "source" is a relative path inside the extracted bundle directory.
- "destination" is the absolute path on the target QA or PROD VM.
- "service_restart" is the systemd service name Person 2's agent will restart.


BUNDLE CONTENTS (for Person 2)
--------------------------------
After extracting a bundle, the layout is:

it490_staging_v<N>/
├── manifest.json
├── frontend/
│   └── index.html
└── backend/
    └── api.php

Extract command:
    tar -xzf it490_v<N>.tgz

The manifest.json is at the root of the extracted directory.
All source paths in the manifest are relative to that root.


VERSION REFERENCE (for Person 3)
----------------------------------
v1 — Working release. Health check should pass. Mark as PASS.
v2 — Intentionally broken. Backend returns HTTP 500. Health check should fail. Mark as FAIL and trigger rollback to v1.
v3 — Fixed release. Deploy to QA first, verify PASS, then deploy to PROD.


RULES FOR ADDING FILES TO A BUNDLE
------------------------------------
1. Place frontend files under app-src/frontend/
2. Place backend files under app-src/backend/
3. Update the manifest template section in build_bundle.sh if new file paths are added.
4. Notify Person 2 if destination paths on the target VM change.
5. Notify Person 3 if the manifest schema changes.
