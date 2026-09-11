---
template: atlas-article.html
atlas_article: true
title: Files
atlas_article_heading_id: files
atlas_component_group: Connect Systems
atlas_component_owner: Application and Adapter
atlas_component_dependencies: Optional Flysystem, Symfony Filesystem, or Laravel filesystem
atlas_article_context: Application · Adapter
atlas_article_lead: Choose durable named file storage or local filesystem operations explicitly; they are different ports with different failure boundaries.
atlas_article_requires: PHP 8.5+
atlas_article_optional: league/flysystem, symfony/filesystem, laravel/framework
atlas_article_package: johnnickell/fight-common
atlas_relationship_source_label: Adapter
atlas_relationship_source: Flysystem, Symfony, or Laravel
atlas_relationship_target_label: Application ports
atlas_relationship_target: FileStorage and Filesystem
atlas_relationship_description: Storage and local filesystem adapters fulfill separate application contracts
atlas_relationship_caption: FileStorage addresses durable logical objects; Filesystem performs host path operations. Neither is a remote transfer session.
atlas_consequential_label: Data boundary
atlas_consequential_message: Cross-storage move copies before deleting the source and is not atomic; verify recovery, permissions, and retention for consequential data.
atlas_next_steps:
  - label: Choose the right file port
    href: "#filestorage-interface"
  - label: Register named storage
    href: "#storageservice-registry"
  - label: Operate local paths
    href: "#filesystem-interface"
  - label: Transfer remote files
    href: "../file-transfer/"
  - label: Configure framework support
    href: "../../frameworks/framework-support/"
atlas_local_contents:
  - label: File storage
    href: "#filestorage-interface"
  - label: Storage registry
    href: "#storageservice-registry"
  - label: Flysystem adapter
    href: "#flysystemstorage"
  - label: Storage failures
    href: "#filestorage-exceptions"
  - label: Filesystem port
    href: "#filesystem-interface"
  - label: Symfony adapter
    href: "#symfonyfilesystem"
  - label: Installation
    href: "#installation"
  - label: Usage examples
    href: "#usage-examples"
---

Files contains two intentionally separate capabilities. `FileStorage` works with logical paths in durable
stores and can be registered by name through `StorageService`. `Filesystem` performs operating-system path
work such as directories, permissions, links, and local reads. Use File Transfer for FTP or SFTP sessions.

--8<-- "docs/files.md"
