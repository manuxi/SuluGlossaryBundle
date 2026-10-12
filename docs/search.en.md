# Search

The bundle registers two reindex providers for the SEAL search of Sulu:

- `GlossaryEntryAdminReindexProvider`: the draft content in the index `admin` (global search in the administration).
- `GlossaryEntryWebsiteReindexProvider`: the live content in the index `website`, for every webspace.

The indexes are updated when an entry is saved, published, unpublished or deleted. Rebuild them with:

```console
bin/console sulu:search:reindex
```

The `admin` and the `website` index have to exist in the configuration of `cmsig_seal` of your project (they are part of the Sulu skeleton). For tests the memory adapter (`cmsig/seal-memory-adapter`) can be used.
