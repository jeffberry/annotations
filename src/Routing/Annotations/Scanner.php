<?php namespace Collective\Annotations\Routing\Annotations;

use ReflectionClass;
use Symfony\Component\Finder\Finder;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Doctrine\Common\Annotations\SimpleAnnotationReader;

class Scanner {

	/**
	 * The path to scan for annotations.
	 *
	 * @var array
	 */
	protected $scan;

	/**
	 * Create a new scanner instance.
	 *
	 * @param  array  $scan
	 * @return void
	 */
	public function __construct(array $scan)
	{
		$this->scan = $scan;

		foreach (Finder::create()->files()->in(__DIR__.'/Annotations') as $file)
		{
			AnnotationRegistry::registerFile($file->getRealPath());
		}
	}

	/**
	 * Create a new scanner instance.
	 *
	 * @param  array  $scan
	 * @return static
	 */
	public static function create(array $scan)
	{
		return new static($scan);
	}

	/**
	 * Convert the scanned annotations into route definitions.
	 *
	 * @return string
	 */
	public function getRouteDefinitions()
	{
		$output = '';

		foreach ($this->getEndpointsInClasses($this->getReader()) as $endpoint)
		{
			$output .= $endpoint->toRouteDefinition().PHP_EOL.PHP_EOL;
		}

		return trim($output);
	}

	/**
	 * Scan the directory and generate the route manifest.
	 *
	 * @param  \Doctrine\Common\Annotations\SimpleAnnotationReader  $reader
	 * @return \Collective\Annotations\Routing\Annotations\EndpointCollection
	 */
	protected function getEndpointsInClasses(SimpleAnnotationReader $reader)
	{
		$endpoints = new EndpointCollection;

		foreach ($this->getClassesToScan() as $class)
		{
			$endpoints = $endpoints->merge($this->getEndpointsInClass(
				$class, new AnnotationSet($class, $reader)
			));
		}

		return $endpoints;
	}

	/**
	 * Build the Endpoints for the given class.
	 *
	 * @param  \ReflectionClass  $class
	 * @param  \Collective\Annotations\Routing\Annotations\AnnotationSet  $annotations
	 * @return \Collective\Annotations\Routing\Annotations\EndpointCollection
	 */
	protected function getEndpointsInClass(ReflectionClass $class, AnnotationSet $annotations)
	{
		$endpoints = new EndpointCollection;

		foreach ($annotations->method as $method => $methodAnnotations)
			$this->addEndpoint($endpoints, $class, $method, $methodAnnotations);

		foreach ($annotations->class as $annotation)
			$annotation->modifyCollection($endpoints, $class);

		return $endpoints;
	}

	/**
	 * Create a new endpoint in the collection.
	 *
	 * @param  \Collective\Annotations\Routing\Annotations\EndpointCollection  $endpoints
	 * @param  \ReflectionClass  $class
	 * @param  string  $method
	 * @param  array  $annotations
	 * @return void
	 */
	protected function addEndpoint(EndpointCollection $endpoints, ReflectionClass $class,
                                   $method, array $annotations)
	{
		$endpoints->push($endpoint = new MethodEndpoint([
			'reflection' => $class, 'method' => $method, 'uses' => $class->name.'@'.$method
		]));

		foreach ($annotations as $annotation)
			$annotation->modify($endpoint, $class->getMethod($method));
	}

	/**
	 * Get all of the ReflectionClass instances in the scan array.
	 *
	 * @return array
	 */
	protected function getClassesToScan()
	{
		$classes = [];

		foreach ($this->scan as $scan)
		{
			try
			{
				$classes[] = new ReflectionClass($scan);
			}
			catch (Exception $e)
			{
				//
			}
		}

		return $classes;
	}

	/**
	 * Get an annotation reader instance.
	 *
	 * @return \Doctrine\Common\Annotations\SimpleAnnotationReader
	 */
	protected function getReader()
	{
		with($reader = new SimpleAnnotationReader)
				->addNamespace('Collective\Annotations\Routing\Annotations\Annotations');

		return $reader;
	}

}
