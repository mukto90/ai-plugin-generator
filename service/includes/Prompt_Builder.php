<?php
/**
 * Builds the full system + user prompt for AI providers.
 * All WPCS rules, multi-file format instructions, and self-review
 * guidance live here — not in the client plugin.
 *
 * @package PluginDaddy_Service
 */

namespace PluginDaddy_Service;

defined( 'ABSPATH' ) || exit;

class Prompt_Builder {

	public function build( $requirements, array $meta = array() ) {
		return array(
			'system' => $this->system_prompt(),
			'user'   => $this->user_prompt( $requirements, $meta ),
		);
	}

	private function system_prompt() {
		return <<<PROMPT
You are an expert WordPress plugin developer. Your job is to write a complete, production-ready WordPress plugin from the user's requirements.

Rules you must follow strictly:
- Follow WordPress Coding Standards (WPCS) for PHP, CSS, and JS.
- Do NOT use Composer, npm, or any external dependencies. The generated plugin must be fully self-contained.
- The plugin must work on PHP 7.4+ and WordPress 5.8+.
- Always include a proper plugin header in the main PHP file.
- Use clean, modern UI/UX for any frontend or admin output.
- Sanitize all inputs, escape all outputs, use nonces for form submissions.
- Never introduce fatal errors, undefined functions, missing brackets, or incorrect hook usage.

Output format:
- If you produce multiple files, separate each one with a heading on its own line: === filename.ext ===
- Immediately after the heading, put the file's contents inside a fenced code block (triple backticks).
- The main plugin file's name must match the plugin slug (e.g., my-plugin/my-plugin.php).

Before responding, self-review your code for:
- Syntax errors
- Undefined functions or classes
- Missing brackets or semicolons
- Incorrect hook names or signatures
- Anything that could crash a WordPress site
PROMPT;
	}

	private function user_prompt( $requirements, array $meta ) {
		$name        = isset( $meta['name'] ) ? $meta['name'] : 'My Plugin';
		$slug        = isset( $meta['slug'] ) ? $meta['slug'] : 'my-plugin';
		$version     = isset( $meta['version'] ) ? $meta['version'] : '0.1.0';
		$author      = isset( $meta['author'] ) ? $meta['author'] : 'Anonymous';
		$description = isset( $meta['description'] ) ? $meta['description'] : '';

		return sprintf(
			"Generate a WordPress plugin with these details:\n\nName: %s\nSlug: %s\nVersion: %s\nAuthor: %s\nDescription: %s\n\nRequirements:\n%s",
			$name,
			$slug,
			$version,
			$author,
			$description,
			$requirements
		);
	}
}
