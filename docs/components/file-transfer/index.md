---
template: atlas-article.html
atlas_article: true
title: File Transfer
atlas_article_heading_id: file-transfer
atlas_component_group: Connect Systems
atlas_component_owner: Application and Adapter
atlas_component_dependencies: Optional phpseclib for SFTP; PHP FTP extension for FTP
atlas_article_context: Application · Adapter
atlas_article_lead: Move bytes through a remote FTP or SFTP session while keeping credentials and protocol behavior outside application code.
atlas_article_requires: PHP 8.5+
atlas_article_optional: phpseclib/phpseclib, ext-ftp
atlas_article_package: johnnickell/fight-common
atlas_relationship_source_label: Adapter
atlas_relationship_source: SFTP or FTP session
atlas_relationship_target_label: Application port
atlas_relationship_target: FileTransport
atlas_relationship_description: Protocol adapters fulfill the remote file transport contract
atlas_relationship_caption: The application chooses the remote operation; the adapter owns connection state, protocol errors, and resource translation.
atlas_consequential_label: Operational boundary
atlas_consequential_message: Remote writes, moves, and deletes are not transactions; design idempotency, partial-failure recovery, host verification, and credential rotation explicitly.
atlas_next_steps:
  - label: Transfer a file
    href: "#uploading-a-file"
  - label: Choose SFTP or FTP
    href: "#adapters"
  - label: Inspect remote metadata
    href: "#resource"
  - label: Store application files
    href: "../files/"
  - label: Trace transfer failures
    href: "../observability/"
atlas_local_contents:
  - label: File transport
    href: "#filetransport"
  - label: Remote resource
    href: "#resource"
  - label: Resource type
    href: "#resourcetype"
  - label: Service facade
    href: "#filetransferservice"
  - label: Adapters
    href: "#adapters"
  - label: Configuration
    href: "#symfony-configuration"
  - label: Usage examples
    href: "#usage-examples"
---

File Transfer is for stateful remote protocol sessions. It is not the `FileStorage` object-store contract
and it is not the local `Filesystem` path API. Depend on `FileTransport` directly or register named
transports in `FileTransferService` when one use case must address more than one endpoint.

--8<-- "docs/file-transfer.md"
