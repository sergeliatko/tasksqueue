<?php


namespace SergeLiatko\TasksQueue;

/**
 * Class Queue
 *
 * @package SergeLiatko\TasksQueue
 */
class Queue {

	const PREFIX = 'tasks_queue';

	const FIVE_MIN = 300;
	const TEN_MIN = 600;
	const FIFTEEN_MIN = 900;

	/**
	 * @var \SergeLiatko\TasksQueue\Queue $instance
	 */
	protected static $instance;

	/**
	 * @var string[]
	 */
	protected $queues;

	/**
	 * @var string $default
	 */
	protected $default;

	/**
	 * Queue constructor.
	 *
	 * @param array  $queues
	 * @param string $default
	 */
	protected function __construct( array $queues, string $default ) {
		$this->setQueues( $queues );
		$this->setDefault( $default );
		foreach ( $this->getQueues() as $queue ) {
			add_action( $queue, array( $this, 'execute' ), 10, 2 );
		}
	}

	/**
	 * @param callable    $job
	 * @param array|null  $args
	 * @param string|null $queue
	 * @param int|null    $offset
	 *
	 * @return bool
	 */
	public static function add( callable $job, ?array $args = array(), ?string $queue = '', ?int $offset = 0 ): bool {
		$instance = self::getInstance();

		return $instance->schedule( $job, $args, $queue, $offset );
	}

	/**
	 * @param callable    $job
	 * @param array|null  $args
	 * @param string|null $queue
	 * @param int|null    $offset
	 *
	 * @return bool
	 */
	public static function addLater( callable $job, ?array $args = array(), ?string $queue = '', ?int $offset = self::TEN_MIN ): bool {
		return self::add( $job, $args, $queue, $offset );
	}

	/**
	 * @param array  $queues
	 * @param string $default
	 *
	 * @return \SergeLiatko\TasksQueue\Queue
	 */
	public static function getInstance( array $queues = array(), string $default = 'default' ): Queue {
		if ( ! self::$instance instanceof Queue ) {
			if ( ! in_array( $default, $queues ) ) {
				$queues[] = $default;
			}
			self::setInstance( new self( $queues, $default ) );
		}

		return self::$instance;
	}

	/**
	 * @param \SergeLiatko\TasksQueue\Queue $instance
	 */
	protected static function setInstance( Queue $instance ): void {
		self::$instance = $instance;
	}

	/**
	 * @param callable $job
	 * @param array    $args
	 */
	public function execute( callable $job, array $args = array() ) {
		call_user_func_array( $job, $args );
	}

	/**
	 * @param callable    $job
	 * @param array|null  $args
	 * @param string|null $queue
	 * @param int|null    $offset
	 *
	 * @return bool
	 */
	public function schedule( callable $job, ?array $args = array(), ?string $queue = '', ?int $offset = 0 ): bool {
		$queue  = $this->validateQueue( $queue );
		$params = array( $job, $args );
		if ( false === wp_next_scheduled( $queue, $params ) ) {
			return wp_schedule_single_event( time() + $offset, $queue, $params );
		}

		return false;
	}

	/**
	 * @return string[]
	 */
	protected function getQueues(): array {
		return $this->queues;
	}

	/**
	 * @param string[] $queues
	 *
	 * @return Queue
	 */
	protected function setQueues( array $queues ): Queue {
		$this->queues = array_filter( array_map(
			array( $this, 'prefix' ),
			array_unique( $queues )
		) );

		return $this;
	}

	/**
	 * @return string
	 */
	protected function getDefault(): string {
		return $this->default;
	}

	/**
	 * @param string $default
	 *
	 * @return Queue
	 */
	protected function setDefault( string $default ): Queue {
		$this->default = $this->prefix( $default );

		return $this;
	}

	/**
	 * @param string $string
	 *
	 * @return string
	 */
	protected function prefix( $string = '' ): string {
		return sprintf( '%1$s_%2$s', self::PREFIX, $string );
	}

	/**
	 * @param string $queue
	 *
	 * @return string
	 */
	protected function validateQueue( string $queue ): string {
		return in_array( $queue = $this->prefix( $queue ), $this->getQueues() ) ?
			$queue
			: $this->getDefault();
	}

}
