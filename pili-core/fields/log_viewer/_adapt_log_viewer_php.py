from pathlib import Path

src = Path(r"f:/phpstudy_pro/WWW/piliai.com/wp-content/plugins/pilipost/vendor-xun/fields/log_viewer/log_viewer.php")
dst = Path(r"f:/phpstudy_pro/WWW/pilidz.com/wp-content/themes/pilidoc/pili-core/fields/log_viewer/log_viewer.php")
t = src.read_text(encoding="utf-8")
t = t.replace("namespace Pilidoc\\Post\\XUN;", "namespace Pili\\Core;")
t = t.replace("class XUN_Field_log_viewer extends XUN_Fields", "class PILI_Field_log_viewer extends PILI_Fields")
t = t.replace("__( '", "pili__( '")
t = t.replace("esc_html__( '", "pili_esc_html__( '")
t = t.replace("esc_attr__( '", "pili_esc_attr__( '")
t = t.replace(", 'pilipost' )", " )")
t = t.replace("xun-field-safe-wrapper", "pili-field-safe-wrapper")
t = t.replace("xun-log-viewer", "pili-log-viewer")
t = t.replace("xun-log-dd", "pili-log-dd")
t = t.replace("class_exists( XUN::class )", "class_exists( PILI_Setup::class )")
t = t.replace("XUN::get_option", "PILI_Setup::get_option")
t = t.replace("XUN_Setup::$dir", "PILI_Setup::$dir")
t = t.replace("XUN_Setup::$url", "PILI_Setup::$url")
t = t.replace("XUN_Field_toast", "PILI_Field_toast")
t = t.replace("PILIDOC_POST_XUN_VERSION", "PILI_CORE_VERSION")
t = t.replace("'pilidoc-post-xun-field-log-viewer'", "pili_asset_handle( 'field-log-viewer' )")
t = t.replace("'pilidoc-post-xun-field-toast'", "pili_asset_handle( 'field-toast' )")
t = t.replace(
    """		wp_localize_script(
			'pilidoc-post-xun-field-log-viewer',
			'xunLogViewerField',""",
    """		$handle = pili_asset_handle( 'field-log-viewer' );
		pili_localize_bag(
			$handle,
			'log_viewer',""",
)
# leftover wp_localize if first replace missed handle string already changed
t = t.replace(
    """		wp_localize_script(
			pili_asset_handle( 'field-log-viewer' ),
			'xunLogViewerField',""",
    """		$handle = pili_asset_handle( 'field-log-viewer' );
		pili_localize_bag(
			$handle,
			'log_viewer',""",
)
# enqueue script still uses old handle variable - fix enqueue block more carefully later
dst.parent.mkdir(parents=True, exist_ok=True)
dst.write_text(t, encoding="utf-8")
print("php written", len(t))
