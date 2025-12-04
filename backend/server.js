const express = require('express');
const { exec } = require('child_process');
const path = require('path');
const cors = require('cors');
const app = express();

// Configuration
const PORT = 3001;
const FRONTEND_PATH = path.join(__dirname, '../frontend/build');

app.use(cors());
app.use(express.json());
app.use(express.static(FRONTEND_PATH));

// --- API Endpoints ---

// 1. System Status (Mocked for Windows/Linux compatibility)
app.get('/api/status', (req, res) => {
    res.json({
        cpu: Math.floor(Math.random() * 30) + 10, // Simulated CPU
        ram: { used: 4, total: 16 },
        storage: "120GB Free"
    });
});

// 2. Trigger Update
app.post('/api/update', (req, res) => {
    console.log("Update requested...");
    // Runs the update script from the root directory
    exec('bash ../update.sh', (error, stdout, stderr) => {
        if (error) {
            console.error(`Update error: ${error}`);
            return res.status(500).json({ error: 'Update failed' });
        }
        res.json({ message: 'Update started. Service will restart shortly.', log: stdout });
    });
});

// 3. Serve the OS UI for any other request
app.get('*', (req, res) => {
    res.sendFile(path.join(FRONTEND_PATH, 'index.html'));
});

app.listen(PORT, () => {
    console.log(`AMPOS running at http://localhost:${PORT}`);
});
