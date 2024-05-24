<?php

namespace n2n\cache\impl\persistence;

enum DboCacheDataSize {
	/**
	 * Gets translated to a VARCHAR column
	 */
	case STRING;
	/**
	 * Gets translated to a TEXT column
	 */
	case TEXT;
}