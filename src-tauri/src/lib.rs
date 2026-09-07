use std::net::TcpStream;
use std::process::{Child, Command};
use std::sync::Mutex;
use std::time::Duration;
use tauri::{Manager, RunEvent};

#[cfg(target_os = "windows")]
use std::os::windows::process::CommandExt;

// 0x08000000 = CREATE_NO_WINDOW di Windows agar jendela CMD hitam tidak muncul
#[cfg(target_os = "windows")]
const CREATE_NO_WINDOW: u32 = 0x08000000;

struct ServerState(Mutex<Option<Child>>);

fn is_port_open(port: u16) -> bool {
    TcpStream::connect_timeout(
        &format!("127.0.0.1:{}", port).parse().unwrap(),
        Duration::from_millis(200),
    )
    .is_ok()
}

fn find_laravel_root() -> std::path::PathBuf {
    if std::path::Path::new("artisan").exists() {
        return std::path::PathBuf::from(".");
    }
    if std::path::Path::new("../artisan").exists() {
        return std::path::PathBuf::from("..");
    }
    if let Ok(exe_path) = std::env::current_exe() {
        if let Some(exe_dir) = exe_path.parent() {
            if exe_dir.join("artisan").exists() {
                return exe_dir.to_path_buf();
            }
            if exe_dir.join("../artisan").exists() {
                return exe_dir.join("..");
            }
            if exe_dir.join("../../artisan").exists() {
                return exe_dir.join("../..");
            }
            if exe_dir.join("../../../artisan").exists() {
                return exe_dir.join("../../..");
            }
        }
    }
    std::path::PathBuf::from("..")
}

fn start_php_server() -> Option<Child> {
    if is_port_open(8000) {
        println!("[DEVArchitect Tauri] Server lokal pada port 8000 sudah aktif.");
        return None;
    }

    let laravel_root = find_laravel_root();
    println!("[DEVArchitect Tauri] Menyalakan PHP background server pada port 8000 di {:?}", laravel_root);
    let mut cmd = Command::new("php");
    cmd.current_dir(&laravel_root);
    cmd.args(["artisan", "serve", "--port=8000", "--host=127.0.0.1"]);

    #[cfg(target_os = "windows")]
    cmd.creation_flags(CREATE_NO_WINDOW);

    match cmd.spawn() {
        Ok(child) => {
            // Tunggu sebentar hingga port 8000 merespons (maksimal 10 detik)
            for _ in 0..50 {
                if is_port_open(8000) {
                    println!("[DEVArchitect Tauri] Server PHP siap!");
                    break;
                }
                std::thread::sleep(Duration::from_millis(200));
            }
            Some(child)
        }
        Err(e) => {
            eprintln!("[DEVArchitect Tauri] Gagal menyalakan PHP server: {}", e);
            None
        }
    }
}

#[cfg_attr(mobile, tauri::mobile_entry_point)]
pub fn run() {
    let server_child = start_php_server();
    let server_state = ServerState(Mutex::new(server_child));

    tauri::Builder::default()
        .manage(server_state)
        .plugin(tauri_plugin_dialog::init())
        .plugin(tauri_plugin_process::init())
        .setup(|app| {
            if cfg!(debug_assertions) {
                app.handle().plugin(
                    tauri_plugin_log::Builder::default()
                        .level(log::LevelFilter::Info)
                        .build(),
                )?;
            }
            Ok(())
        })
        .build(tauri::generate_context!())
        .expect("error while running tauri application")
        .run(|app_handle, event| {
            if let RunEvent::ExitRequested { .. } = event {
                // Matikan proses PHP background saat aplikasi ditutup
                if let Some(state) = app_handle.try_state::<ServerState>() {
                    if let Ok(mut lock) = state.0.lock() {
                        if let Some(mut child) = lock.take() {
                            let _ = child.kill();
                            println!("[DEVArchitect Tauri] Proses PHP background dimatikan dengan aman.");
                        }
                    }
                }
            }
        });
}
