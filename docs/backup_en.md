# Backup integration

Email contributes the `email` structured-configuration provider. It archives portable delivery settings such as enabled state, SMTP transport shape, sender, application URL, notification integration, worker options, and menu settings.

The email outbox and delivery attempts are deliberately excluded so old messages are never sent again after restore. Environment secrets and host-specific credentials must still be supplied securely on the target installation; encrypted backup protects the archive in transit but does not turn an environment secret into portable configuration.

Use component scope to move only Email configuration, or site scope as part of a complete application restore. Run a test message after restore and before enabling a production worker.
