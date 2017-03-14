/*
 *	ParticleController Object 
 *
 */

function ParticleController(id) {
	
	var canvasObj = document.getElementById('canvas');
	this.canvas   = canvasObj.getContext('2d');
	
	this.particles = new Array;
	
	this.height = canvasObj.height;
	this.width  = canvasObj.width;
	this.bounce = true;	
	
	this.drawInterval = 15;
	
	this.gravConstant = 1.5;
	this.NucleusCount = 3;
	
	this.setStage();
	
	console.log('ParticleController initiated');
	
	colors = [ 'ff0000', '00ff00', '0000ff', 'ff00ff', 'ffff00', '00ffff' ];
	
	for (i=0; i<this.NucleusCount; i++) {
		this.addParticle(
			Math.floor( Math.random() * this.width ),
			Math.floor( Math.random() * this.height ),
			Math.floor( Math.random() * 6 )+1,
			colors[i],
			5
		);
	}
	
	var PContObj = $(this);
	this.drawTimer = setInterval("PCont.draw();", this.drawInterval);
}

ParticleController.prototype.setStage = function() {
		
	this.canvas.fillStyle = "rgb(0,0,0)";
	this.canvas.fillRect(0,0,this.width,this.height);
	
	console.log("Canvas filled");
	
}

ParticleController.prototype.addParticle = function(x, y, mass, color, radius) {
	this.particles.push( new Nucleus(x, y, mass, color, radius) );
}

ParticleController.prototype.draw = function() {
	
	this.canvas.fillStyle = '#000000';
	this.canvas.fillRect(0, 0, this.width, this.height);
	
	for (index in this.particles) {
		particle = this.particles[index];
		if (particle.alive == true) {			
			particle.calculateMotion(this);			
			particle.move(this);
			particle.draw( this.canvas );
		} else {
			particle.respawn(this);
		}
	}
}

/*
 *	Particle Object 
 *
 */


function Particle(x, y, mass, color, radius) {
	
	this.x     = x;
	this.y     = y;
	
	this.calculateAngle( Math.floor(Math.random() * 360), Math.random() * 3 );
		
	this.mass   = mass;	
	this.radius = radius;
	this.color = color;
	this.alive = true;
	
	this.type  = 'particle';
	
	console.log('Particle initiated:', this.x, this.y, this.color);
}

Particle.prototype.calculateAngle = function(angle, velocity) {	
	this.velX = Math.sin(angle) * velocity;
	this.velY = 0-Math.cos(angle) * velocity;	
}

Particle.prototype.kill = function(controller) {	
	this.alive = false;
	this.x = -100;
	this.y = -100;
}

Particle.prototype.respawn = function(controller) {
	
	var side = Math.floor( Math.random() * 4 );
	
	switch (side) {
		case 0: // top
			this.x = Math.random() * controller.width;
			this.y = 1;
			this.velX = 1;
			break;
		case 1: // right
			this.x = controller.width-1;
			this.y = Math.random() * controller.height;
			this.velY = -1;
			break;
		case 2: // bottom
			this.x = Math.random() * controller.width;
			this.y = controller.height-1;
			this.velX = -1;
			break;
		case 3: // left
			this.x = 1;
			this.y = Math.random() * controller.height;
			this.velY = 1;
			break;
	}
	this.calculateAngle( Math.floor(Math.random() * 360), 1 );
	this.alive = true;
	
}


/*
 *	Nucleus Object 
 *
 */


Nucleus.prototype = new Particle();
Nucleus.prototype.constructor = Nucleus;
function Nucleus(x, y, mass, color, radius) {
	
	this.x     = x;
	this.y     = y;
	
	this.calculateAngle( Math.floor(Math.random() * 360), Math.random() * 3 );
		
	this.mass   = mass;	
	this.radius = radius+mass;
	this.color = color;
	this.alive = true;
	this.speed = 0;
	
	this.trail = new Array;	
	
	this.type  = 'nucleus';
	
	console.log('Nucleus initiated:', this.x, this.y, this.color);
}

Nucleus.prototype.draw = function(canvas) {
	
	for(index in this.trail) {
		var electron = this.trail[index];		
		if (electron != undefined) {
			electron.move();
			if (!electron.draw(canvas)) {
				delete this.trail[index];
			}
		}		
	}
	
	this.speed = Math.abs( Math.sqrt( Math.pow(this.velX, 2) + Math.pow(this.velY, 2) ) );
	
	
	// draw halo
	canvas.beginPath();
	var haloRadius = (this.speed * 2) + this.radius;
	canvas.arc( this.x, this.y, haloRadius, 0, 2*Math.PI, false);
	var halo = canvas.createRadialGradient( this.x, this.y, 1, this.x, this.y, haloRadius);
	
	halo.addColorStop(0, '#'+this.color);
	halo.addColorStop(0.6, '#'+this.color);
	halo.addColorStop(1, '#000000');
	canvas.globalAlpha = 0.3;
	canvas.fillStyle = halo;
	canvas.fill();
	
	canvas.beginPath();
	canvas.arc(this.x, this.y, this.radius, 0, 2*Math.PI, false);
	
	var offset = (this.radius * 0.25);
	var whiteRadius = Math.min( this.speed / 4, this.radius-2 );
	var grad   = canvas.createRadialGradient(this.x-offset, this.y-offset, this.speed, this.x, this.y, this.radius);
	grad.addColorStop(0, '#ffffff');
	grad.addColorStop(1, '#'+this.color);
	canvas.globalAlpha = 1;
	canvas.fillStyle = grad;
	
	//canvas.fillStyle = "#"+this.color;
	canvas.fill();
}

Nucleus.prototype.calculateMotion = function(controller) {
	
	
	for (index in controller.particles) {
		var particle = controller.particles[index];
		
		if (this.alive === true && particle.alive === true && particle != this) {
			var distX    = this.x - particle.x;
			var distY    = this.y - particle.y;
			var distMax  = Math.max( Math.abs(distX), Math.abs(distY) );
			var distance = Math.sqrt( Math.pow( Math.abs(distX), 2) + Math.pow( Math.abs(distY), 2));
			if (distance < (this.radius + particle.radius)) {
				
				this.emit(150, 7);
				this.respawn(controller);
				particle.emit(150, 7);
				particle.respawn(controller);				
				
			} else {
				
				var force  = (( this.mass * particle.mass ) / distance ) * controller.gravConstant;
				var forceX = (distX / distance) * force;
				var forceY = (distY / distance) * force;
				
				this.velX -= (forceX / this.mass);
				this.velY -= (forceY / this.mass);
				
		
			}
			
			
		}
	}
	
}

Nucleus.prototype.move = function(controller) {
	
	this.x += this.velX;
	this.y += this.velY;
	var halfRadius = this.radius / 2;
	
	
	if (controller.bounce == false) {
		if (this.x < 0) this.x = controller.width;
		if (this.y < 0) this.y = controller.height;
		
		if (this.x > controller.width) this.x = 0;
		if (this.y > controller.height) this.y = 0;
	} else {
		if (this.x < 0 || this.x > controller.width) {
			this.velX = -(this.velX * 1);
			this.x += this.velX * 1.2;
		}
		if (this.y < 0 || this.y > controller.height) {
			this.velY = -(this.velY * 1);
			this.y += this.velY * 1.2;;
		}
	}
	
	this.emit(2, 1.5);
	
}

Nucleus.prototype.emit = function(count, force) {
	count = (count == undefined) ? 1 : count ;
	for(i=0;i<count;i++) {
		var x = this.x + ((Math.random() * this.radius) - (this.radius/2));
		var y = this.y + ((Math.random() * this.radius) - (this.radius/2));
		
		var color, colorSeed = Math.floor( Math.random() * 15);
		if (colorSeed < this.speed) {
			color = 'ffffff'; 
		} else {
			color = this.color; 
		}
		
		this.trail.push( new Electron(x, y, color, force));
	}

}

/*
 *	Electron Code Body
 *
 */


Electron.prototype = new Particle();
Electron.prototype.constructor = Electron;
function Electron(x, y, color, force) {
	
	force = (force == undefined) ? 1 : force ;
	
	this.x     = x;
	this.y     = y;
	
	this.calculateAngle( Math.floor(Math.random() * 360), Math.random() * (0.2 * force) );
	this.color = color;

	this.alive = true;
	this.life = 75 + (Math.floor( Math.random() * 40) - 20);		
	
	this.type  = 'electron';
	
	
}

Electron.prototype.move = function(controller) {
	
	
	this.velX = this.velX * 0.99;
	this.velY = this.velY * 0.99;
	
	this.x += this.velX;
	this.y += this.velY;
	
}

Electron.prototype.draw = function(canvas) {
	var lifeTrans  = Math.min(100, this.life) / 100;
	var lifeRadius = Math.max(1, (-this.life + 100) / 33);
	canvas.beginPath();
	canvas.arc(this.x, this.y, lifeRadius, 0, 2*Math.PI, false);		
	canvas.globalAlpha = lifeTrans;
	canvas.fillStyle = "#"+this.color;
	canvas.fill();
	this.life -= 1;
	return (this.life > 0) ? true : false ;
}


/*
 *	Main Code Body
 *
 */

var Pcont;
$(document).ready( function() {
	
	PCont = new ParticleController('canvas');
		
	
	
	
	
})
