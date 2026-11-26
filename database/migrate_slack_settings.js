/**
 * Database Migration Script: Add Slack Settings to company_settings
 * Tailoring Management System (TailorPro)
 * 
 * Usage:
 *   node database/migrate_slack_settings.js
 * 
 * You can pass your credentials via environment variables or interactive arguments:
 *   SLACK_BOT_TOKEN="xoxb-..." SLACK_CHANNEL_ID="C0..." node database/migrate_slack_settings.js
 */

const mysql = require('mysql2/promise');
const readline = require('readline');
const fs = require('fs');
const path = require('path');

// Try reading DB config from config/database.php if available, otherwise use defaults
function parseDbConfig() {
    const config = {
        host: process.env.DB_HOST || 'localhost',
        user: process.env.DB_USER || 'root',
        password: process.env.DB_PASSWORD !== undefined ? process.env.DB_PASSWORD : '',
        database: process.env.DB_NAME || 'tailorpro'
    };

    try {
        const phpConfigPath = path.join(__dirname, '..', 'config', 'database.php');
        if (fs.existsSync(phpConfigPath)) {
            const content = fs.readFileSync(phpConfigPath, 'utf8');
            const hostMatch = content.match(/private\s+\$host\s*=\s*['"]([^'"]+)['"]/);
            const dbMatch = content.match(/private\s+\$db_name\s*=\s*['"]([^'"]+)['"]/);
            const userMatch = content.match(/private\s+\$username\s*=\s*['"]([^'"]+)['"]/);
            const passMatch = content.match(/private\s+\$password\s*=\s*['"]([^'"]*)['"]/);

            if (hostMatch) config.host = hostMatch[1];
            if (dbMatch) config.database = dbMatch[1];
            if (userMatch) config.user = userMatch[1];
            if (passMatch) config.password = passMatch[1];
        }
    } catch (e) {
        // Fall back to default config
    }

    return config;
}

function prompt(question) {
    const rl = readline.createInterface({
        input: process.stdin,
        output: process.stdout
    });
    return new Promise(resolve => rl.question(question, ans => {
        rl.close();
        resolve(ans.trim());
    }));
}

async function runMigration() {
    console.log('====================================================');
    console.log('🚀 TailorPro - Slack Settings Migration');
    console.log('====================================================\n');

    const dbConfig = parseDbConfig();
    console.log(`Connecting to MySQL database: ${dbConfig.database} @ ${dbConfig.host}...`);

    let connection;
    try {
        connection = await mysql.createConnection(dbConfig);
        console.log('Connected to database successfully.\n');
    } catch (err) {
        console.error('❌ Failed to connect to database:', err.message);
        console.error('Check your database credentials or set DB_HOST, DB_USER, DB_PASSWORD, DB_NAME env vars.');
        process.exit(1);
    }

    try {
        // Step 1: Add columns if not exists
        console.log('1. Checking and adding Slack columns to `company_settings` table...');
        
        const [columns] = await connection.query(`
            SELECT COLUMN_NAME 
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'company_settings'
        `, [dbConfig.database]);

        const existingCols = columns.map(c => c.COLUMN_NAME.toLowerCase());

        if (!existingCols.includes('slack_bot_token')) {
            await connection.query('ALTER TABLE `company_settings` ADD COLUMN `slack_bot_token` VARCHAR(255) NULL DEFAULT NULL AFTER `updated_at`');
            console.log('   Added column `slack_bot_token`.');
        } else {
            console.log('   Column `slack_bot_token` already exists.');
        }

        if (!existingCols.includes('slack_channel_id')) {
            await connection.query('ALTER TABLE `company_settings` ADD COLUMN `slack_channel_id` VARCHAR(100) NULL DEFAULT NULL AFTER `slack_bot_token`');
            console.log('   Added column `slack_channel_id`.');
        } else {
            console.log('   Column `slack_channel_id` already exists.');
        }

        if (!existingCols.includes('slack_webhook_url')) {
            await connection.query('ALTER TABLE `company_settings` ADD COLUMN `slack_webhook_url` VARCHAR(500) NULL DEFAULT NULL AFTER `slack_channel_id`');
            console.log('   Added column `slack_webhook_url`.');
        } else {
            console.log('   Column `slack_webhook_url` already exists.');
        }

        console.log('Columns verified successfully.\n');

        // Step 2: Prompt for Bot Token and Channel ID if not provided in env
        let botToken = process.env.SLACK_BOT_TOKEN || '';
        let channelId = process.env.SLACK_CHANNEL_ID || '';
        let webhookUrl = process.env.SLACK_WEBHOOK_URL || '';

        if (!botToken && !webhookUrl) {
            console.log('----------------------------------------------------');
            console.log('🔑 Enter Slack Credentials to save into database:');
            console.log('(Press Enter to skip if you prefer to set them later)');
            console.log('----------------------------------------------------');
            botToken = await prompt('Enter Slack Bot Token (xoxb-...): ');
            if (botToken) {
                channelId = await prompt('Enter Slack Channel ID (e.g., C0...): ');
            } else {
                webhookUrl = await prompt('Or enter Slack Webhook URL (optional): ');
            }
        }

        if (botToken || channelId || webhookUrl) {
            console.log('\n2. Updating Slack credentials in `company_settings`...');
            
            // Check if a row exists in company_settings
            const [rows] = await connection.query('SELECT id FROM company_settings ORDER BY id ASC LIMIT 1');
            
            if (rows.length > 0) {
                const targetId = rows[0].id;
                await connection.query(`
                    UPDATE company_settings 
                    SET slack_bot_token = ?, 
                        slack_channel_id = ?, 
                        slack_webhook_url = ?
                    WHERE id = ?
                `, [botToken || null, channelId || null, webhookUrl || null, targetId]);
                console.log(`   Updated company_settings (row id: ${targetId}) with your Slack credentials.`);
            } else {
                await connection.query(`
                    INSERT INTO company_settings (business_name, slack_bot_token, slack_channel_id, slack_webhook_url) 
                    VALUES ('TailorPro', ?, ?, ?)
                `, [botToken || null, channelId || null, webhookUrl || null]);
                console.log('   Inserted new row in company_settings with your Slack credentials.');
            }
        } else {
            console.log('\nℹ️ No credentials entered. Columns are ready in database.');
        }

        console.log('\n====================================================');
        console.log(' Migration finished successfully!');
        console.log('====================================================');

    } catch (err) {
        console.error('❌ Migration failed:', err.message);
    } finally {
        await connection.end();
    }
}

runMigration();
