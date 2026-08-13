<?php

declare(strict_types=1);

// HR: Outbox je prolazni red slanja; izvorne postavke prenosi datotečni pružatelj.
// EN: The outbox is a transient delivery queue; the file provider transfers source settings.
return ['providers' => ['heartphrame.backup.provider.email']];
