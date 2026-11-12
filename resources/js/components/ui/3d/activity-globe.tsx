'use client';

import React, { useRef, useMemo } from 'react';
import { Canvas, useFrame } from '@react-three/fiber';
import { Sphere, Line, OrbitControls, Stars } from '@react-three/drei';
import * as THREE from 'three';

function WorldGlobe() {
  const meshRef = useRef<THREE.Mesh>(null!);
  const groupRef = useRef<THREE.Group>(null!);

  useFrame((state, delta) => {
    if (groupRef.current) {
        // Continuous rotation
        groupRef.current.rotation.y += delta * 0.15;
    }
  });

  // Create connection arcs
  const connections = useMemo(() => {
    const lines = [];
    for (let i = 0; i < 20; i++) {
        // Random points on sphere (radius 1.5)
        const phi1 = Math.random() * Math.PI * 2;
        const theta1 = Math.random() * Math.PI;
        const p1 = new THREE.Vector3().setFromSphericalCoords(1.5, theta1, phi1);

        const phi2 = Math.random() * Math.PI * 2;
        const theta2 = Math.random() * Math.PI;
        const p2 = new THREE.Vector3().setFromSphericalCoords(1.5, theta2, phi2);

        // Control point for Bezier (higher than surface)
        const mid = p1.clone().add(p2).multiplyScalar(1.3); 
        
        const curve = new THREE.QuadraticBezierCurve3(p1, mid, p2);
        const points = curve.getPoints(30);
        lines.push(points);
    }
    return lines;
  }, []);

  return (
    <group ref={groupRef}>
      {/* Globe Wireframe */}
      <Sphere args={[1.5, 48, 48]} ref={meshRef}>
        <meshStandardMaterial 
            color="#334155" // Slate-700
            wireframe={true}
            transparent
            opacity={0.15}
        />
      </Sphere>
      
      {/* Inner Globe (Dark) */}
      <Sphere args={[1.48, 48, 48]}>
        <meshBasicMaterial color="#020617" /> {/* Slate-950 */}
      </Sphere>

      {/* Connection Arcs */}
      {connections.map((points, i) => (
        <Line 
            key={i} 
            points={points} 
            color={i % 2 === 0 ? "#60a5fa" : "#c084fc"} // Blue-400 / Purple-400
            lineWidth={1} 
            opacity={0.6}
            transparent
        />
      ))}
      
      {/* Cities/Nodes */}
      {connections.map((points, i) => (
          <mesh key={`p-${i}`} position={points[0]}>
              <sphereGeometry args={[0.03, 16, 16]} />
              <meshBasicMaterial color="#3b82f6" />
          </mesh>
      ))}
    </group>
  );
}

export function ActivityGlobe({ className = "" }: { className?: string }) {
  return (
    <div className={`h-[400px] w-full relative ${className}`}>
      <Canvas camera={{ position: [0, 0, 4], fov: 45 }} gl={{ alpha: true, antialias: true }}>
        <ambientLight intensity={0.5} />
        <pointLight position={[10, 10, 10]} intensity={1} />
        <WorldGlobe />
        <OrbitControls enableZoom={false} enablePan={false} autoRotate autoRotateSpeed={0.5} />
        <Stars radius={100} depth={50} count={1000} factor={4} saturation={0} fade speed={1} />
      </Canvas>
    </div>
  );
}
