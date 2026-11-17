'use strict';

function handleRequiredAssetError(assetName, error, isProduction, logger = console) {
    const message = `[Asset Error] Failed to load ${assetName}: ${error.message}`;
    logger.error(message);
    if (isProduction) {
        throw new Error(message);
    }
}

module.exports = { handleRequiredAssetError };
