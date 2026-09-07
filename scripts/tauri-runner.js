import { spawn } from 'node:child_process';
import net from 'node:net';
import fs from 'node:fs';
import path from 'node:path';

// Pastikan lokasi cargo & rustc selalu ada di PATH runtime
const cargoBin = 'C:\\Users\\Indra\\.cargo\\bin';
if (!process.env.PATH.includes(cargoBin)) {
    process.env.PATH = `${cargoBin};${process.env.PATH}`;
}

const args = process.argv.slice(2);
const isDev = args.includes('dev');

function isPortOpen(port) {
    return new Promise((resolve) => {
        const socket = new net.Socket();
        socket.setTimeout(250);
        socket.once('connect', () => {
            socket.destroy();
            resolve(true);
        });
        socket.once('timeout', () => {
            socket.destroy();
            resolve(false);
        });
        socket.once('error', () => {
            resolve(false);
        });
        socket.connect(port, '127.0.0.1');
    });
}

async function run() {
    let phpProcess = null;
    let viteProcess = null;

    if (isDev) {
        // 1. Pastikan server lokal PHP berjalan di port 8000
        const open = await isPortOpen(8000);
        if (!open) {
            console.log('\x1b[36m[DEVArchitect]\x1b[0m Memulai server lokal PHP di port 8000...');
            phpProcess = spawn('php', ['artisan', 'serve', '--port=8000', '--host=127.0.0.1'], {
                stdio: 'ignore',
                shell: true,
                cwd: process.cwd()
            });

            // Tunggu sampai port 8000 siap
            for (let i = 0; i < 25; i++) {
                if (await isPortOpen(8000)) {
                    console.log('\x1b[32m[DEVArchitect]\x1b[0m Server PHP siap di http://127.0.0.1:8000');
                    break;
                }
                await new Promise((r) => setTimeout(r, 200));
            }
        }

        // 2. Pastikan Vite dev server (Hot Reload) berjalan di port 5173
        const viteOpen = await isPortOpen(5173);
        if (!viteOpen) {
            console.log('\x1b[36m[DEVArchitect]\x1b[0m Memulai Vite dev server (Hot Reload)...');
            const viteBin = path.join(process.cwd(), 'node_modules', '.bin', 'vite.cmd');
            viteProcess = spawn(viteBin, [], {
                stdio: 'ignore',
                shell: true,
                cwd: process.cwd()
            });

            // Tunggu sampai port 5173 siap
            for (let i = 0; i < 25; i++) {
                if (await isPortOpen(5173)) {
                    console.log('\x1b[32m[DEVArchitect]\x1b[0m Vite dev server siap (Hot Reload aktif)');
                    break;
                }
                await new Promise((r) => setTimeout(r, 200));
            }
        }
    }

    const child = spawn('npx.cmd', ['tauri', ...args], {
        stdio: 'inherit',
        shell: true,
        env: process.env,
    });

    const cleanup = () => {
        if (phpProcess) {
            try {
                spawn('taskkill', ['/pid', phpProcess.pid, '/T', '/F'], { stdio: 'ignore' });
            } catch (e) {}
        }
        if (viteProcess) {
            try {
                spawn('taskkill', ['/pid', viteProcess.pid, '/T', '/F'], { stdio: 'ignore' });
            } catch (e) {}
        }
        try {
            const hotFile = path.join(process.cwd(), 'public', 'hot');
            if (fs.existsSync(hotFile)) {
                fs.unlinkSync(hotFile);
            }
        } catch (e) {}
    };

    child.on('exit', (code) => {
        cleanup();
        process.exit(code || 0);
    });

    process.on('SIGINT', () => {
        cleanup();
        process.exit(0);
    });
    process.on('SIGTERM', () => {
        cleanup();
        process.exit(0);
    });
}

run();

